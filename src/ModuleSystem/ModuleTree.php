<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

use function Safe\file_get_contents;
use function Safe\glob;
use function Safe\json_decode;

/**
 * Discovers modules and resolves their execution order based on composer.json dependencies.
 */
final class ModuleTree
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
        return new self(base_path('app-modules'));
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

        $directories = glob($this->appModulesPath . '/*', GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            $composerPath = $directory . '/composer.json';

            if (! file_exists($composerPath)) {
                continue;
            }

            $composerContent = file_get_contents($composerPath);

            $composer = json_decode($composerContent, true);
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
        $graph = $this->getDependencyGraph();

        // Calculate in-degree for each node
        // If A depends on B, then A has an in-degree from B (B must come before A)
        $inDegree = array_fill_keys(array_keys($graph), 0);

        foreach ($graph as $module => $dependencies) {
            // Each dependency adds to the in-degree of the dependent module
            $inDegree[$module] = count($dependencies);
        }

        // Queue with nodes having no dependencies (in-degree 0)
        $queue = [];
        foreach ($inDegree as $module => $degree) {
            if ($degree === 0) {
                $queue[] = $module;
            }
        }

        $result = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            $result[] = $current;

            // For each module that depends on current, reduce its in-degree
            foreach ($graph as $module => $dependencies) {
                if (in_array($current, $dependencies, true)) {
                    $inDegree[$module]--;

                    if ($inDegree[$module] === 0) {
                        $queue[] = $module;
                    }
                }
            }
        }

        // If not all nodes are processed, there's a cycle
        if (count($result) !== count($graph)) {
            $cycles = $this->detectCircularDependencies();

            throw new CircularDependencyException($cycles);
        }

        return $result;
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
        $graph = $this->getDependencyGraph();
        $cycles = [];
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($graph) as $module) {
            if (! isset($visited[$module])) {
                $this->detectCyclesDfs($module, $graph, $visited, $recursionStack, [], $cycles);
            }
        }

        return $this->uniqueCycles($cycles);
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

    /**
     * @param  array<string, array<string>>  $graph
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $recursionStack
     * @param  array<string>  $path
     * @param  array<array<string>>  $cycles
     */
    private function detectCyclesDfs(
        string $current,
        array $graph,
        array &$visited,
        array &$recursionStack,
        array $path,
        array &$cycles,
    ): void {
        $visited[$current] = true;
        $recursionStack[$current] = true;
        $path[] = $current;

        foreach ($graph[$current] ?? [] as $dependency) {
            if ($dependency === $current) {
                continue;
            }

            if (! isset($visited[$dependency])) {
                $this->detectCyclesDfs($dependency, $graph, $visited, $recursionStack, $path, $cycles);
            } elseif (isset($recursionStack[$dependency]) && $recursionStack[$dependency]) {
                $cycleStart = array_search($dependency, $path, true);

                if ($cycleStart !== false) {
                    $cycle = array_slice($path, $cycleStart);
                    $cycle[] = $dependency;
                    $cycles[] = $cycle;
                }
            }
        }

        $recursionStack[$current] = false;
    }

    /**
     * Remove duplicate cycles by normalizing them.
     *
     * @param  array<array<string>>  $cycles
     * @return array<array<string>>
     */
    private function uniqueCycles(array $cycles): array
    {
        $normalized = [];

        foreach ($cycles as $cycle) {
            $cycleWithoutDuplicate = array_slice($cycle, 0, -1);

            if ($cycleWithoutDuplicate === []) {
                continue;
            }

            $minIndex = 0;
            $minValue = $cycleWithoutDuplicate[0];

            foreach ($cycleWithoutDuplicate as $index => $value) {
                if ($value < $minValue) {
                    $minValue = $value;
                    $minIndex = $index;
                }
            }

            $normalizedCycle = array_merge(
                array_slice($cycleWithoutDuplicate, $minIndex),
                array_slice($cycleWithoutDuplicate, 0, $minIndex)
            );

            $key = implode(' -> ', $normalizedCycle);

            if (! isset($normalized[$key])) {
                $normalized[$key] = $cycle;
            }
        }

        return array_values($normalized);
    }
}
