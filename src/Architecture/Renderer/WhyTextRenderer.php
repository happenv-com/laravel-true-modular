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

    public function render(ArchitectureReport $report): string
    {
        /** @var WhyReport $report */
        if ($report->path === null) {
            return sprintf('%s has no dependency path to %s', $report->from, $report->to);
        }

        return implode("\n  ↓\n", $report->path);
    }
}
