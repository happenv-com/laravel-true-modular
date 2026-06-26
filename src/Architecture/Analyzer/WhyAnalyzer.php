<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Analyzer;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Report\WhyReport;

final class WhyAnalyzer
{
    public function analyze(ArchitectureIndex $index, string $from, string $to): WhyReport
    {
        $index->assertKnown($from);
        $index->assertKnown($to);

        return new WhyReport($from, $to, $index->graph()->path($from, $to));
    }
}
