<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Analyzer;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;
use InvalidArgumentException;

final class ImpactAnalyzer
{
    public function analyze(ArchitectureIndex $index, string $module): ImpactReport
    {
        if (! $index->has($module)) {
            throw new InvalidArgumentException(sprintf('Unknown module [%s].', $module));
        }

        $graph = $index->graph();

        $direct = $graph->dependents($module);
        $indirect = array_values(array_diff($graph->transitiveDependents($module), $direct));
        sort($indirect);

        return new ImpactReport($module, $direct, $indirect);
    }
}
