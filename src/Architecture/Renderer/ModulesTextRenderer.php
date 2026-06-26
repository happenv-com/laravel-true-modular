<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\ModulesReport;

/**
 * Plain numbered list of modules in resolution order — the `--simple` /
 * `--format=text` view for `module:list`. The richer boxed table stays in the
 * command because it uses the interactive console table component.
 */
final class ModulesTextRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'text';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof ModulesReport;
    }

    public function render(ArchitectureReport $report): string
    {
        /** @var ModulesReport $report */
        $direction = $report->reverse ? 'dependents first' : 'dependencies first';
        $lines = [sprintf('Modules in order (%s):', $direction), ''];

        foreach ($report->modules as $index => $module) {
            $lines[] = sprintf('  %d. %s', $index + 1, $module['name']);
        }

        return implode("\n", $lines);
    }
}
