<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;

final class RendererRegistry
{
    /** @var array<ArchitectureRenderer> */
    private array $renderers;

    /**
     * @param  iterable<ArchitectureRenderer>  $renderers
     */
    public function __construct(iterable $renderers)
    {
        $this->renderers = is_array($renderers) ? $renderers : iterator_to_array($renderers, false);
    }

    public function get(string $format, ArchitectureReport $report): ArchitectureRenderer
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->format() === $format && $renderer->supports($report)) {
                return $renderer;
            }
        }

        throw new UnsupportedFormatException(sprintf(
            'No renderer supports format [%s] for report [%s].',
            $format,
            $report->schemaName(),
        ));
    }
}
