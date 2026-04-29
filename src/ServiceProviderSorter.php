<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular;

use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Support\ServiceProvider;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

/**
 * Sorts service providers according to module dependency order.
 *
 * Takes an array of service providers and reorders them so that
 * module providers are sorted in topological order
 * (dependencies first), while preserving non-modular providers
 * in their original relative positions.
 */
final class ServiceProviderSorter
{
    /**
     * @var array<string, string>|null Cached namespace to module name map
     */
    private ?array $namespaceMap = null;

    public function __construct(
        private readonly ModuleTree $moduleTree,
    ) {}

    /**
     * Sort service providers according to module dependency order.
     *
     * @param  array<ServiceProvider>  $providers
     * @return array<ServiceProvider>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    public function sort(array $providers): array
    {
        $topologicalOrder = $this->moduleTree->getTopologicalOrder();
        $moduleOrderMap = array_flip($topologicalOrder);

        // Separate app module providers from others
        $moduleProviders = [];
        $otherProviders = [];

        foreach ($providers as $provider) {
            $moduleName = $this->getModuleName($provider);

            if ($moduleName !== null && isset($moduleOrderMap[$moduleName])) {
                $moduleProviders[] = [
                    'module' => $moduleName,
                    'order' => $moduleOrderMap[$moduleName],
                    'provider' => $provider,
                ];
            } else {
                $otherProviders[] = $provider;
            }
        }

        // Sort module providers by topological order
        usort($moduleProviders, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        // Extract sorted providers
        $sortedModuleProviders = array_map(
            fn (array $item): ServiceProvider => $item['provider'],
            $moduleProviders
        );

        // Return other providers first, then sorted module providers
        return [...$otherProviders, ...$sortedModuleProviders];
    }

    /**
     * Get the module package name for a service provider.
     *
     * @return string|null The module name (e.g., 'vendor/module') or null if not a module provider
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getModuleName(ServiceProvider $provider): ?string
    {
        $className = $provider::class;
        $namespaceMap = $this->getNamespaceMap();

        // Sort by namespace length (longest first) to match most specific namespace
        $namespaces = array_keys($namespaceMap);
        usort($namespaces, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($namespaces as $namespace) {
            if (str_starts_with($className, $namespace)) {
                return $namespaceMap[$namespace];
            }
        }

        return null;
    }

    /**
     * Build the namespace to module name map from autoload config.
     *
     * @return array<string, string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function getNamespaceMap(): array
    {
        if ($this->namespaceMap !== null) {
            return $this->namespaceMap;
        }

        $this->namespaceMap = [];
        $modules = $this->moduleTree->getAllModules();

        foreach ($modules as $moduleName => $moduleData) {
            $autoload = $moduleData['composer']['autoload']['psr-4'] ?? [];

            foreach (array_keys($autoload) as $namespace) {
                // Normalize namespace (ensure it ends with backslash)
                $normalizedNamespace = rtrim((string) $namespace, '\\') . '\\';
                $this->namespaceMap[$normalizedNamespace] = $moduleName;
            }
        }

        return $this->namespaceMap;
    }

    /**
     * Get all app module providers from the given list.
     *
     * @param  array<ServiceProvider>  $providers
     * @return array<string, array<ServiceProvider>> Keyed by module name
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function groupByModule(array $providers): array
    {
        $grouped = [];

        foreach ($providers as $provider) {
            $moduleName = $this->getModuleName($provider);

            if ($moduleName !== null) {
                $grouped[$moduleName][] = $provider;
            }
        }

        return $grouped;
    }
}
