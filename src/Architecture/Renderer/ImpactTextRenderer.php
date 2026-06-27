<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;

final class ImpactTextRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'text';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof ImpactReport;
    }

    public function render(ArchitectureReport $report, RenderContext $context): string
    {
        /** @var ImpactReport $report */
        $lines = [$context->display($report->module), ''];

        $lines[] = 'Direct:';
        $lines = [...$lines, ...$this->indent($report->direct, $context)];
        $lines[] = '';
        $lines[] = 'Indirect:';
        $lines = [...$lines, ...$this->indent($report->indirect, $context)];
        $lines[] = '';
        $lines[] = sprintf('Total affected: %d', $report->total());

        return implode("\n", $lines);
    }

    /**
     * @param  array<string>  $items
     * @return array<string>
     */
    private function indent(array $items, RenderContext $context): array
    {
        if ($items === []) {
            return ['  -'];
        }

        return array_map(static fn (string $item): string => '  '.$context->display($item), $items);
    }
}
