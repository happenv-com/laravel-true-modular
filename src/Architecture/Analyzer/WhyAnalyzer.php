<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Analyzer;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Report\WhyReport;
use InvalidArgumentException;

final class WhyAnalyzer
{
    public function analyze(ArchitectureIndex $index, string $from, string $to): WhyReport
    {
        foreach ([$from, $to] as $module) {
            if (! $index->has($module)) {
                throw new InvalidArgumentException(sprintf('Unknown module [%s].', $module));
            }
        }

        return new WhyReport($from, $to, $index->graph()->path($from, $to));
    }
}
