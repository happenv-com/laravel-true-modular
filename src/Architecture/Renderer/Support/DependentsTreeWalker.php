<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer\Support;

/**
 * Pre-order depth-first walk over a dependents adjacency map, shared by the
 * text and tree graph renderers. The walker owns the traversal and the
 * path-based cycle guard; each renderer supplies its own line formatting.
 */
final readonly class DependentsTreeWalker
{
    /**
     * @param  array<string, array<string>>  $dependents  node => sorted dependents
     */
    public function __construct(private array $dependents) {}

    /**
     * A walker that visits every reachable node exactly once.
     *
     * The full walk repeats a node under every path that reaches it, which grows
     * with the number of paths, not nodes — one module deep in a wide graph can be
     * printed thousands of times. This keeps a spanning tree instead: each node sits
     * under the parent that reaches it first breadth-first, i.e. along a shortest
     * path from a root (ties go to the parent visited first, roots in order, children
     * in their sorted order). A root reachable from another root stays a root.
     *
     * @param  array<string>  $roots
     * @param  array<string, array<string>>  $dependents  node => sorted dependents
     */
    public static function eachNodeOnce(array $roots, array $dependents): self
    {
        $placed = array_fill_keys($roots, true);
        $children = [];
        $queue = array_values($roots);

        // A cursor, not array_shift(): shifting reindexes the queue on every step.
        for ($next = 0; $next < count($queue); $next++) {
            $node = $queue[$next];

            foreach ($dependents[$node] ?? [] as $child) {
                if (isset($placed[$child])) {
                    continue;
                }

                $placed[$child] = true;
                $children[$node][] = $child;
                $queue[] = $child;
            }
        }

        return new self($children);
    }

    /**
     * Visit every node reachable from each root (roots included), pre-order.
     *
     * The visitor receives the node, the list of "is last child" flags for the
     * chain of ancestors below the root (empty for a root), and whether the node
     * is itself a root. Those flags carry enough information to render either
     * plain indentation or box-drawing glyphs.
     *
     * @param  array<string>  $roots
     * @param  callable(string $node, list<bool> $ancestorsAreLast, bool $isRoot): void  $visit
     */
    public function walk(array $roots, callable $visit): void
    {
        foreach ($roots as $root) {
            $visit($root, [], true);
            $this->descend($root, [], $visit, [$root => true]);
        }
    }

    /**
     * @param  list<bool>  $ancestorsAreLast
     * @param  callable(string, list<bool>, bool): void  $visit
     * @param  array<string, true>  $visited
     */
    private function descend(string $node, array $ancestorsAreLast, callable $visit, array $visited): void
    {
        $children = $this->dependents[$node] ?? [];
        $lastIndex = count($children) - 1;

        foreach ($children as $index => $child) {
            $childAncestors = [...$ancestorsAreLast, $index === $lastIndex];

            $visit($child, $childAncestors, false);

            if (isset($visited[$child])) {
                continue;
            }

            $this->descend($child, $childAncestors, $visit, [...$visited, $child => true]);
        }
    }
}
