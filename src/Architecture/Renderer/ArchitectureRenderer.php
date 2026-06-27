<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;

interface ArchitectureRenderer
{
    public function format(): string;

    public function supports(ArchitectureReport $report): bool;

    public function render(ArchitectureReport $report, RenderContext $context): string;
}
