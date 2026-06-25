<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

final class TreeRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'tree';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report): string
    {
        /** @var GraphReport $report */
        $lines = [];

        foreach ($report->roots as $root) {
            $lines[] = $root;
            $this->children($root, $report->dependents, '', $lines, [$root => true]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, array<string>>  $dependents
     * @param  array<string>  $lines
     * @param  array<string, true>  $visited
     */
    private function children(string $node, array $dependents, string $prefix, array &$lines, array $visited = []): void
    {
        $children = $dependents[$node] ?? [];
        $last = count($children) - 1;

        foreach ($children as $index => $child) {
            $isLast = $index === $last;
            $lines[] = $prefix.($isLast ? '└── ' : '├── ').$child;

            if (isset($visited[$child])) {
                continue;
            }

            $visited[$child] = true;
            $this->children($child, $dependents, $prefix.($isLast ? '    ' : '│   '), $lines, $visited);
        }
    }
}
