<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Renderer\Support\EmitsGraphEdges;
use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

final class MermaidRenderer implements ArchitectureRenderer
{
    use EmitsGraphEdges;

    public function format(): string
    {
        return 'mermaid';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report, RenderContext $context): string
    {
        /** @var GraphReport $report */
        $edges = $this->edges($report, $context, static fn (string $from, string $to): string => sprintf('%s --> %s', $from, $to));

        return implode("\n", ['graph TD', '', ...$edges]);
    }
}
