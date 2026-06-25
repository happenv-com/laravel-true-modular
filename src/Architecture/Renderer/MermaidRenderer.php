<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

final class MermaidRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'mermaid';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report): string
    {
        /** @var GraphReport $report */
        $lines = ['graph TD', ''];

        foreach ($report->dependents as $node => $dependents) {
            foreach ($dependents as $dependent) {
                $lines[] = sprintf('%s --> %s', $node, $dependent);
            }
        }

        return implode("\n", $lines);
    }
}
