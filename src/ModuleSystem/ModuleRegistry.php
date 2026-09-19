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

    /**
     * @var array<string, array<string>>|null Cached graph including `require-dev`
     */
    private ?array $fullDependencyGraph = null;

    /**
     * @var array<string>|null Cached topological order
     */
    private ?array $topologicalOrder;

    private ?string $signature;

    /**
     * `$modules` is a scan already made — by {@see ModuleRegistryCache} — to answer from
     * instead of scanning; null scans on first use. `$topologicalOrder` is their order as the
     * cache computed it; null computes it on first use. `$signature` identifies both, as the
     * cache wrote it; see {@see signature()}.
     *
     * @param  array<string, array{name: string, path: string, composer: array<string, mixed>}>|null  $modules
     * @param  array<string>|null  $topologicalOrder
     */
    public function __construct(
        private readonly string $appModulesPath,
        ?array $modules = null,
        ?array $topologicalOrder = null,
        ?string $signature = null,
    ) {
        $this->modules = $modules;
        $this->topologicalOrder = $topologicalOrder;
        $this->signature = $signature;
    }

    /**
     * Get the default instance using Laravel's base path.
     *
     * Answers from the module cache written by `true-modular:cache` when there is one and it
     * still describes the modules on disk; scans otherwise.
     */
    public static function make(): self
    {
        $appModulesPath = base_path(Application::getModulesDirectory());
        $cached = ModuleRegistryCache::make()->load($appModulesPath);

        return new self(
            $appModulesPath,
            $cached['modules'] ?? null,
            $cached['topological_order'] ?? null,
            $cached['signature'] ?? null,
        );
    }

    /**
     * What identifies these modules and their order, for a caller that keeps something it
     * derived from them for as long as the process lives — the provider sorter keeps the order
     * it put the providers in, which a process booting the application again and again (a test
     * suite boots one per test) would otherwise work out on every boot.
     *
     * Only the module cache vouches for it, so it is null for modules that were scanned, and
     * after {@see clearCache()}: nothing says two scans found the same modules.
     */
    public function signature(): ?string
    {
        return $this->signature;
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
        return $this->declaredModules($moduleName, 'require');
    }

    /**
     * Dependencies a module declares for its TESTS only (`require-dev`).
     *
     * Deliberately NOT folded into {@see self::getDependencies()}: that method feeds
     * {@see self::getTopologicalOrder()}, which orders service providers at boot. A
     * `require-dev` edge legitimately points back at a dependent — a module's tests
     * commonly exercise it through one — so adding these edges there would make the
     * provider graph cyclic and boot would throw.
     *
     * @return array<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getDevDependencies(string $moduleName): array
    {
        return $this->declaredModules($moduleName, 'require-dev');
    }

    /**
     * The module names one composer section of a module declares, keeping only those
     * that are themselves modules here.
     *
     * @return array<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function declaredModules(string $moduleName, string $section): array
    {
        $modules = $this->getAllModules();

        if (! isset($modules[$moduleName])) {
            return [];
        }

        $declared = $modules[$moduleName]['composer'][$section] ?? [];
        $dependencies = [];

        foreach (array_keys($declared) as $dependency) {
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
     * The dependency graph including `require-dev` edges.
     *
     * This is the graph a test-scoped runner and a boundary gate answer from: a module
     * whose tests reach another module is affected by it just as surely as one whose
     * shipped code does. It is NOT the graph providers are ordered from — see
     * {@see self::getDevDependencies()} for why those must stay apart.
     *
     * @return array<string, array<string>>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getFullDependencyGraph(): array
    {
        if ($this->fullDependencyGraph !== null) {
            return $this->fullDependencyGraph;
        }

        $this->fullDependencyGraph = [];

        foreach ($this->getModuleNames() as $moduleName) {
            $this->fullDependencyGraph[$moduleName] = array_values(array_unique([
                ...$this->getDependencies($moduleName),
                ...$this->getDevDependencies($moduleName),
            ]));
        }

        return $this->fullDependencyGraph;
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
        if ($this->topologicalOrder !== null) {
            return $this->topologicalOrder;
        }

        $order = TopologicalSort::order($this->getDependencyGraph());

        if ($order === null) {
            throw new CircularDependencyException($this->detectCircularDependencies());
        }

        return $this->topologicalOrder = $order;
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
     * Defaults to the SHIPPED graph, so existing callers — provider ordering, the
     * health check, the graph command — keep seeing exactly what they saw before.
     * Pass `$includeDev` to ask about the graph a test-scoped runner and a boundary
     * gate use, where `require-dev` edges can and do close cycles.
     *
     * @param  bool  $includeDev  also walk `require-dev` edges
     * @return array<array<string>> Array of circular dependency paths
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function detectCircularDependencies(bool $includeDev = false): array
    {
        return TopologicalSort::cycles(
            $includeDev ? $this->getFullDependencyGraph() : $this->getDependencyGraph(),
        );
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
        $this->fullDependencyGraph = null;
        $this->topologicalOrder = null;
        $this->signature = null;
    }
}
