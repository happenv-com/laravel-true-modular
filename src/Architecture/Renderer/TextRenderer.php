<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;
use Happenv\LaravelTrueModular\Architecture\Report\WhyReport;

final class TextRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'text';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof ImpactReport
            || $report instanceof WhyReport
            || $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report): string
    {
        return match (true) {
            $report instanceof ImpactReport => $this->impact($report),
            $report instanceof WhyReport => $this->why($report),
            $report instanceof GraphReport => $this->graph($report),
            default => '',
        };
    }

    private function impact(ImpactReport $report): string
    {
        $lines = [$report->module, ''];

        $lines[] = 'Direct:';
        $lines = [...$lines, ...$this->indent($report->direct)];
        $lines[] = '';
        $lines[] = 'Indirect:';
        $lines = [...$lines, ...$this->indent($report->indirect)];
        $lines[] = '';
        $lines[] = sprintf('Total affected: %d', $report->total());

        return implode("\n", $lines);
    }

    private function why(WhyReport $report): string
    {
        if ($report->path === null) {
            return sprintf('%s has no dependency path to %s', $report->from, $report->to);
        }

        return implode("\n  ↓\n", $report->path);
    }

    private function graph(GraphReport $report): string
    {
        $lines = [];

        foreach ($report->roots as $root) {
            $this->appendTree($root, $report->dependents, '', $lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, array<string>>  $dependents
     * @param  array<string>  $lines
     */
    private function appendTree(string $node, array $dependents, string $prefix, array &$lines): void
    {
        $lines[] = $prefix.$node;

        foreach ($dependents[$node] ?? [] as $child) {
            $this->appendTree($child, $dependents, $prefix.'  ', $lines);
        }
    }

    /**
     * @param  array<string>  $items
     * @return array<string>
     */
    private function indent(array $items): array
    {
        if ($items === []) {
            return ['  -'];
        }

        return array_map(static fn (string $item): string => '  '.$item, $items);
    }
}
