<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer\Support;

use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

/**
 * Shared edge enumeration for graph renderers that emit one line per edge
 * (Graphviz DOT, Mermaid). Each renderer supplies only its edge formatter.
 */
trait EmitsGraphEdges
{
    /**
     * @param  callable(string $from, string $to): string  $format
     * @return list<string>
     */
    protected function edges(GraphReport $report, callable $format): array
    {
        $lines = [];

        foreach ($report->dependents as $node => $dependents) {
            foreach ($dependents as $dependent) {
                $lines[] = $format((string) $node, $dependent);
            }
        }

        return $lines;
    }
}
