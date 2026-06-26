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

    public function render(ArchitectureReport $report): string
    {
        /** @var ImpactReport $report */
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
