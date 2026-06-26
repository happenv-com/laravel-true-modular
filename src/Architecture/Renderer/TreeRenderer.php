<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Renderer\Support\DependentsTreeWalker;
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

        new DependentsTreeWalker($report->dependents)->walk(
            $report->roots,
            static function (string $node, array $ancestorsAreLast, bool $isRoot) use (&$lines): void {
                $lines[] = $isRoot ? $node : self::glyphPrefix($ancestorsAreLast).$node;
            },
        );

        return implode("\n", $lines);
    }

    /**
     * Turn the per-ancestor "is last child" flags into box-drawing indentation:
     * ancestor levels become `│   ` / `    `, and the node's own level becomes
     * the `├── ` / `└── ` connector.
     *
     * @param  list<bool>  $ancestorsAreLast
     */
    private static function glyphPrefix(array $ancestorsAreLast): string
    {
        $prefix = '';
        $lastIndex = count($ancestorsAreLast) - 1;

        foreach ($ancestorsAreLast as $index => $isLast) {
            if ($index === $lastIndex) {
                $prefix .= $isLast ? '└── ' : '├── ';
            } else {
                $prefix .= $isLast ? '    ' : '│   ';
            }
        }

        return $prefix;
    }
}
