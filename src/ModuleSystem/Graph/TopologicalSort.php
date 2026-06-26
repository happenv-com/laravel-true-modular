<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem\Graph;

/**
 * Pure topological ordering and cycle detection over a dependency adjacency map
 * (`node => list of dependencies`). Single home for the graph algorithms that
 * were previously duplicated between ModuleTree and the architecture layer.
 *
 * The order of the returned sequence follows the input key order for nodes that
 * are otherwise unordered, so callers control tie-breaking by the order in which
 * they build the adjacency map.
 */
final class TopologicalSort
{
    /**
     * Order nodes so that every dependency precedes its dependents (Kahn's
     * algorithm). Returns null when the graph contains a cycle.
     *
     * @param  array<string, array<string>>  $adjacency  node => dependencies
     * @return array<string>|null
     */
    public static function order(array $adjacency): ?array
    {
        $inDegree = [];

        foreach ($adjacency as $node => $dependencies) {
            $inDegree[$node] = count($dependencies);
        }

        $queue = [];

        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }

        $result = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            $result[] = $current;

            foreach ($adjacency as $node => $dependencies) {
                if (in_array($current, $dependencies, strict: true)) {
                    $inDegree[$node]--;

                    if ($inDegree[$node] === 0) {
                        $queue[] = $node;
                    }
                }
            }
        }

        return count($result) === count($adjacency) ? $result : null;
    }

    /**
     * Find distinct dependency cycles, each normalized to start at its smallest
     * member so the same cycle is reported once regardless of entry point.
     *
     * @param  array<string, array<string>>  $adjacency  node => dependencies
     * @return array<array<string>>
     */
    public static function cycles(array $adjacency): array
    {
        $cycles = [];
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($adjacency) as $node) {
            if (! isset($visited[$node])) {
                self::detect($node, $adjacency, $visited, $recursionStack, [], $cycles);
            }
        }

        return self::unique($cycles);
    }

    /**
     * @param  array<string, array<string>>  $adjacency
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $recursionStack
     * @param  array<string>  $path
     * @param  array<array<string>>  $cycles
     */
    private static function detect(
        string $current,
        array $adjacency,
        array &$visited,
        array &$recursionStack,
        array $path,
        array &$cycles,
    ): void {
        $visited[$current] = true;
        $recursionStack[$current] = true;
        $path[] = $current;

        foreach ($adjacency[$current] ?? [] as $dependency) {
            if ($dependency === $current) {
                continue;
            }

            if (! isset($visited[$dependency])) {
                self::detect($dependency, $adjacency, $visited, $recursionStack, $path, $cycles);
            } elseif (isset($recursionStack[$dependency]) && $recursionStack[$dependency]) {
                $cycleStart = array_search($dependency, $path, strict: true);

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
     * @param  array<array<string>>  $cycles
     * @return array<array<string>>
     */
    private static function unique(array $cycles): array
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
