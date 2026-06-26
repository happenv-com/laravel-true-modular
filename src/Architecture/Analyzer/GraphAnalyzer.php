<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Analyzer;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

final class GraphAnalyzer
{
    public function analyze(ArchitectureIndex $index, ?string $root = null): GraphReport
    {
        if ($root !== null) {
            $index->assertKnown($root);
        }

        $graph = $index->graph();

        $nodes = $root !== null ? [$root, ...$graph->transitiveDependents($root)] : $graph->nodes();

        $nodeSet = array_fill_keys($nodes, value: true);
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
