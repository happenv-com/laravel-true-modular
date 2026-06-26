<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\Graph\TopologicalSort;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

use function Safe\file_get_contents;
use function Safe\glob;
use function Safe\json_decode;

/**
 * Discovers modules and resolves their execution order based on composer.json dependencies.
 */
final class ModuleRegistry
{
    /**
     * @var array<string, array<string, mixed>>|null Cached module data
     */
    private ?array $modules = null;

    /**
     * @var array<string, array<string>>|null Cached dependency graph
     */
    private ?array $dependencyGraph = null;

    public function __construct(
        private readonly string $appModulesPath,
    ) {}

    /**
     * Get the default instance using Laravel's base path.
     */
    public static function make(): self
    {
        return new self(base_path(Application::getModulesDirectory()));
    }

    /**
     * Get all discovered modules.
     *
     * @return array<string, array{name: string, path: string, composer: array<string, mixed>}>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getAllModules(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $this->modules = [];

        $directories = glob($this->appModulesPath.'/*', GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            $composerPath = $directory.'/composer.json';

            if (! file_exists($composerPath)) {
                continue;
            }

            $composerContent = file_get_contents($composerPath);

            $composer = json_decode($composerContent, associative: true);
            if (! is_array($composer)) {
                continue;
            }

            if (! isset($composer['name'])) {
                continue;
            }

            // Filter by module type
            $expectedType = Application::getModuleComposerType();
            if (($composer['type'] ?? null) !== $expectedType) {
                continue;
            }

            $moduleName = $composer['name'];

            $this->modules[$moduleName] = [
                'composer' => $composer,
                'name' => $moduleName,
                'path' => $directory,
            ];
        }

        return $this->modules;
    }

    /**
     * Get module names only.
     *
     * @return array<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getModuleNames(): array
    {
        return array_keys($this->getAllModules());
    }

    /**
     * Get all PSR-4 namespaces declared by a module, each normalized to a
     * trailing backslash. Empty when the module declares no PSR-4 autoload.
     *
     * @return array<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getModuleNamespaces(string $moduleName): array
    {
        $modules = $this->getAllModules();
        $autoload = $modules[$moduleName]['composer']['autoload']['psr-4'] ?? [];

        return array_map(
            static fn (int|string $namespace): string => rtrim((string) $namespace, '\\').'\\',
            array_keys($autoload),
        );
    }

    /**
     * Get the primary (first) PSR-4 namespace of a module, or null when none is declared.
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getModuleNamespace(string $moduleName): ?string
    {
        return $this->getModuleNamespaces($moduleName)[0] ?? null;
    }

    /**
     * Get dependencies for a specific module.
     *
     * @return array<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getDependencies(string $moduleName): array
    {
        $modules = $this->getAllModules();

        if (! isset($modules[$moduleName])) {
            return [];
        }

        $require = $modules[$moduleName]['composer']['require'] ?? [];
        $dependencies = [];

        foreach (array_keys($require) as $dependency) {
            // Only include if it's an actual module we know about
            if (! isset($modules[$dependency])) {
                continue;
            }

            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Get the full dependency graph.
     *
     * @return array<string, array<string>>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getDependencyGraph(): array
    {
        if ($this->dependencyGraph !== null) {
            return $this->dependencyGraph;
        }

        $this->dependencyGraph = [];

        foreach ($this->getModuleNames() as $moduleName) {
            $this->dependencyGraph[$moduleName] = $this->getDependencies($moduleName);
        }

        return $this->dependencyGraph;
    }

    /**
     * Get modules in topological order (dependencies first).
     *
     * Returns modules ordered so that dependencies come before dependents.
     * Example: core → pim → sale → amazon (core has no deps, amazon depends on sale)
     *
     * Uses Kahn's algorithm for topological sorting.
     *
     * @return array<string>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getTopologicalOrder(): array
    {
        $order = TopologicalSort::order($this->getDependencyGraph());

        if ($order === null) {
            throw new CircularDependencyException($this->detectCircularDependencies());
        }

        return $order;
    }

    /**
     * Get modules in reverse topological order (dependents first).
     *
     * Returns modules ordered so that dependents come before their dependencies.
     * Example: amazon → sale → pim → core (amazon depends on sale, core has no deps)
     *
     * Useful for cleanup/teardown operations or when you need to process
     * leaf modules before their dependencies.
     *
     * @return array<string>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getReverseTopologicalOrder(): array
    {
        return array_reverse($this->getTopologicalOrder());
    }

    /**
     * Detect circular dependencies in the module graph.
     *
     * @return array<array<string>> Array of circular dependency paths
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function detectCircularDependencies(): array
    {
        return TopologicalSort::cycles($this->getDependencyGraph());
    }

    /**
     * Get the module path.
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getModulePath(string $moduleName): ?string
    {
        $modules = $this->getAllModules();

        return $modules[$moduleName]['path'] ?? null;
    }

    /**
     * Clear the cache to force re-discovery of modules.
     */
    public function clearCache(): void
    {
        $this->modules = null;
        $this->dependencyGraph = null;
    }
}
