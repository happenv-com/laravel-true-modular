<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Graph;

use Happenv\LaravelTrueModular\ModuleSystem\Graph\TopologicalSort;

/**
 * @template TNode of string
 */
final class DependencyGraph
{
    /** @var array<string, array<string>> node => sorted direct dependencies */
    private array $adjacency;

    /** @var array<string, array<string>>|null node => sorted direct dependents */
    private ?array $reverseCache = null;

    /**
     * @param  array<string, array<string>>  $adjacency
     */
    public function __construct(array $adjacency)
    {
        $normalized = [];

        foreach ($adjacency as $node => $deps) {
            $deps = array_values(array_unique($deps));
            sort($deps);
            $normalized[(string) $node] = $deps;

            foreach ($deps as $dep) {
                $normalized[$dep] ??= [];
            }
        }

        ksort($normalized);

        $this->adjacency = $normalized;
    }

    /** @return array<string> */
    public function nodes(): array
    {
        return array_keys($this->adjacency);
    }

    public function has(string $node): bool
    {
        return isset($this->adjacency[$node]);
    }

    /** @return array<string> */
    public function dependencies(string $node): array
    {
        return $this->adjacency[$node] ?? [];
    }

    /** @return array<string> */
    public function dependents(string $node): array
    {
        return $this->reverse()[$node] ?? [];
    }

    /** @return array<string> */
    public function transitiveDependencies(string $node): array
    {
        return $this->reachable($node, $this->adjacency);
    }

    /** @return array<string> */
    public function transitiveDependents(string $node): array
    {
        return $this->reachable($node, $this->reverse());
    }

    /**
     * Nodes in dependency order (dependencies before dependents), or null when
     * the graph contains a cycle. Ties break alphabetically (adjacency is
     * normalized in the constructor).
     *
     * @return array<string>|null
     */
    public function topologicalOrder(): ?array
    {
        return TopologicalSort::order($this->adjacency);
    }

    /**
     * Distinct dependency cycles, empty when the graph is acyclic.
     *
     * @return array<array<string>>
     */
    public function cycles(): array
    {
        return TopologicalSort::cycles($this->adjacency);
    }

    public function fanIn(string $node): int
    {
        return count($this->dependents($node));
    }

    public function fanOut(string $node): int
    {
        return count($this->dependencies($node));
    }

    /**
     * Shortest dependency path from $from to $to (inclusive endpoints), or null.
     *
     * @return array<string>|null
     */
    public function path(string $from, string $to): ?array
    {
        if (! $this->has($from) || ! $this->has($to)) {
            return null;
        }

        if ($from === $to) {
            return [$from];
        }

        $queue = [[$from]];
        $visited = [$from => true];

        while ($queue !== []) {
            $currentPath = array_shift($queue);
            $last = $currentPath[count($currentPath) - 1];

            foreach ($this->dependencies($last) as $dependency) {
                if ($dependency === $to) {
                    return [...$currentPath, $dependency];
                }

                if (! isset($visited[$dependency])) {
                    $visited[$dependency] = true;
                    $queue[] = [...$currentPath, $dependency];
                }
            }
        }

        return null;
    }

    /**
     * Longest downstream dependency chain length (in edges). Leaf => 0.
     */
    public function dependencyDepth(string $node): int
    {
        return $this->depth($node, []);
    }

    /**
     * @param  array<string, bool>  $stack
     */
    private function depth(string $node, array $stack): int
    {
        if (isset($stack[$node])) {
            return 0;
        }

        $stack[$node] = true;
        $max = 0;

        foreach ($this->dependencies($node) as $dependency) {
            $max = max($max, 1 + $this->depth($dependency, $stack));
        }

        return $max;
    }

    /**
     * @param  array<string, array<string>>  $graph
     * @return array<string>
     */
    private function reachable(string $node, array $graph): array
    {
        $result = [];
        $seen = [];
        $stack = $graph[$node] ?? [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;
            $result[] = $current;

            foreach ($graph[$current] ?? [] as $next) {
                if (! isset($seen[$next])) {
                    $stack[] = $next;
                }
            }
        }

        sort($result);

        return $result;
    }

    /** @return array<string, array<string>> */
    private function reverse(): array
    {
        if ($this->reverseCache !== null) {
            return $this->reverseCache;
        }

        $reverse = array_fill_keys(array_keys($this->adjacency), []);

        foreach ($this->adjacency as $node => $deps) {
            foreach ($deps as $dependency) {
                $reverse[$dependency][] = $node;
            }
        }

        foreach ($reverse as &$dependents) {
            sort($dependents);
        }
        unset($dependents);

        return $this->reverseCache = $reverse;
    }
}
