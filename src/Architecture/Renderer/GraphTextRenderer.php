<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Renderer\Support\DependentsTreeWalker;
use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

final class GraphTextRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'text';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report, RenderContext $context): string
    {
        /** @var GraphReport $report */
        $lines = [];

        (new DependentsTreeWalker($report->dependents))->walk(
            $report->roots,
            function (string $node, array $ancestorsAreLast) use (&$lines, $context): void {
                $lines[] = str_repeat('  ', count($ancestorsAreLast)).$context->display($node);
            },
        );

        return implode("\n", $lines);
    }
}
