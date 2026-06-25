<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Analyzer;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;
use InvalidArgumentException;

final class GraphAnalyzer
{
    public function analyze(ArchitectureIndex $index, ?string $root = null): GraphReport
    {
        $graph = $index->graph();

        if ($root !== null && ! $graph->has($root)) {
            throw new InvalidArgumentException(sprintf('Unknown module [%s].', $root));
        }

        if ($root !== null) {
            $nodes = [$root, ...$graph->transitiveDependents($root)];
        } else {
            $nodes = $graph->nodes();
        }

        $nodeSet = array_fill_keys($nodes, true);
        $dependents = [];

        foreach ($nodes as $node) {
            $dependents[$node] = array_values(array_filter(
                $graph->dependents($node),
                static fn (string $dependent): bool => isset($nodeSet[$dependent]),
            ));
        }

        ksort($dependents);

        if ($root !== null) {
            $roots = [$root];
        } else {
            $roots = array_values(array_filter(
                $nodes,
                static fn (string $node): bool => $graph->fanOut($node) === 0,
            ));
            sort($roots);
        }

        return new GraphReport($roots, $dependents, $root);
    }
}
