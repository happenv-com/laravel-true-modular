<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Renderer\Support\EmitsGraphEdges;
use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

final class DotRenderer implements ArchitectureRenderer
{
    use EmitsGraphEdges;

    public function format(): string
    {
        return 'dot';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report): string
    {
        /** @var GraphReport $report */
        $edges = $this->edges($report, static fn (string $from, string $to): string => sprintf('    "%s" -> "%s"', $from, $to));

        return implode("\n", ['digraph {', '', ...$edges, '', '}']);
    }
}
