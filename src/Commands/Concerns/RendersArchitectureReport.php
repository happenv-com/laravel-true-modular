<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands\Concerns;

use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Renderer\UnsupportedFormatException;
use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;

trait RendersArchitectureReport
{
    protected function schemaVersionValid(ArchitectureReport $report): bool
    {
        $requested = (int) $this->option('schema-version');

        if ($requested !== $report->schemaVersion()) {
            $this->error(sprintf(
                'Unsupported schema version [%d]. Supported: %d.',
                $requested,
                $report->schemaVersion(),
            ));

            return false;
        }

        return true;
    }

    protected function output(ArchitectureReport $report, string $defaultFormat): int
    {
        if (! $this->schemaVersionValid($report)) {
            return self::FAILURE;
        }

        $format = (string) ($this->option('format') ?: $defaultFormat);

        /** @var RendererRegistry $registry */
        $registry = $this->renderers;

        try {
            $renderer = $registry->get($format, $report);
        } catch (UnsupportedFormatException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($renderer->render($report));

        return self::SUCCESS;
    }
}
