<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\WhyReport;

final class WhyTextRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'text';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof WhyReport;
    }

    public function render(ArchitectureReport $report, RenderContext $context): string
    {
        /** @var WhyReport $report */
        if ($report->path === null) {
            return sprintf('%s has no dependency path to %s', $context->display($report->from), $context->display($report->to));
        }

        return implode("\n  ↓\n", array_map(static fn (string $node): string => $context->display($node), $report->path));
    }
}
