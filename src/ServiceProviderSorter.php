<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular;

use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Happenv\LaravelTrueModular\ModuleSystem\NamespaceMatcher;
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
     * @var NamespaceMatcher<string>|null Cached index over the namespace to module name map
     */
    private ?NamespaceMatcher $namespaceMatcher = null;

    /**
     * Provider classes in the order {@see sort()} put them, per module signature and incoming list.
     *
     * The order is a function of nothing but the classes handed in, in the order they came, and
     * of the modules — their dependency order and namespaces, which the registry's signature
     * identifies. A process that boots the application again and again, as a test suite does
     * once per test, therefore sorts the same list once; every later boot only lays its own
     * provider instances out in the kept order. Measured on a host with 115 modules and 286
     * providers: ~0.8 ms of every boot, where the kept order costs ~0.05 ms.
     *
     * Kept only for modules the module cache vouches for ({@see ModuleRegistry::signature()}),
     * and keyed on the whole list, so a provider registered in one boot and not in another makes
     * a different key. Bounded by the distinct lists a process sorts — in practice one.
     *
     * @var array<string, list<class-string<ServiceProvider>>>
     */
    private static array $keptOrders = [];

    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
    ) {}

    /**
     * Sort service providers according to module dependency order.
     *
     * The result is keyed by class name, the shape `Application::$serviceProviders` is read
     * back through: `markAsRegistered()` files each provider under `get_class($provider)`
     * and `getProvider()` is a plain lookup on that key. Returning a list here would leave
     * `getProvider()` answering null for every provider in the application — silently, since
     * a missing key is indistinguishable from a provider that was never registered.
     *
     * As in `markAsRegistered()`, one class means one provider: passing two instances of the
     * same class keeps the last one.
     *
     * @param  array<ServiceProvider>  $providers
     * @return array<class-string<ServiceProvider>, ServiceProvider>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    public function sort(array $providers): array
    {
        $signature = $this->moduleRegistry->signature();

        if ($signature === null) {
            return $this->sortByModuleOrder($providers);
        }

        $key = $signature.':'.hash('xxh128', implode("\n", array_map(
            static fn (ServiceProvider $provider): string => $provider::class,
            $providers,
        )));

        if (! isset(self::$keptOrders[$key])) {
            $sorted = $this->sortByModuleOrder($providers);
            self::$keptOrders[$key] = array_keys($sorted);

            return $sorted;
        }

        // The instances are this boot's own: only the order is kept. A class passed twice keeps
        // its last instance, exactly as the sort below does.
        $byClass = [];

        foreach ($providers as $provider) {
            $byClass[$provider::class] = $provider;
        }

        $sorted = [];

        foreach (self::$keptOrders[$key] as $class) {
            $sorted[$class] = $byClass[$class];
        }

        return $sorted;
    }

    /**
     * @param  array<ServiceProvider>  $providers
     * @return array<class-string<ServiceProvider>, ServiceProvider>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    private function sortByModuleOrder(array $providers): array
    {
        $topologicalOrder = $this->moduleRegistry->getTopologicalOrder();
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

        // Other providers first, then sorted module providers — re-keyed by class name on the
        // way out, because the spread above would otherwise renumber them into a list.
        $sorted = [];

        foreach ([...$otherProviders, ...$sortedModuleProviders] as $provider) {
            $sorted[$provider::class] = $provider;
        }

        return $sorted;
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
        return $this->getNamespaceMatcher()->match($provider::class);
    }

    /**
     * The namespace to module name index, built on first use.
     *
     * Held as an index rather than a plain map because `sort()` asks it one question per
     * registered provider, and rebuilding the index per question would put the whole map
     * back on the hot path that indexing it was meant to take it off.
     *
     * @return NamespaceMatcher<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function getNamespaceMatcher(): NamespaceMatcher
    {
        return $this->namespaceMatcher ??= NamespaceMatcher::for($this->buildNamespaceMap());
    }

    /**
     * Build the namespace to module name map from autoload config.
     *
     * @return array<string, string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function buildNamespaceMap(): array
    {
        $namespaceMap = [];

        foreach (array_keys($this->moduleRegistry->getAllModules()) as $moduleName) {
            foreach ($this->moduleRegistry->getModuleNamespaces($moduleName) as $namespace) {
                $namespaceMap[$namespace] = $moduleName;
            }
        }

        return $namespaceMap;
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
