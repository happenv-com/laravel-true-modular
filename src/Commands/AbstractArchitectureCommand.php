<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Renderer\UnsupportedFormatException;
use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Shared skeleton for the architecture analysis commands. Subclasses build a
 * report from the freshly-built index ({@see buildReport()}) and declare their
 * default output format ({@see defaultFormat()}); this base owns index building,
 * error handling, schema-version validation, format selection and rendering.
 */
abstract class AbstractArchitectureCommand extends Command
{
    public function __construct(
        protected readonly ArchitectureIndexBuilder $builder,
        protected readonly RendererRegistry $renderers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $report = $this->buildReport($this->builder->build());
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->output($report, $this->defaultFormat());
    }

    /**
     * Run this command's analyzer against the index and return its report.
     *
     * @throws InvalidArgumentException for unknown modules / invalid arguments
     */
    abstract protected function buildReport(ArchitectureIndex $index): ArchitectureReport;

    /**
     * Output format used when `--format` is not supplied.
     */
    abstract protected function defaultFormat(): string;

    protected function output(ArchitectureReport $report, string $defaultFormat): int
    {
        if (! $this->schemaVersionValid($report)) {
            return self::FAILURE;
        }

        $format = (string) ($this->option('format') ?: $defaultFormat);

        try {
            $renderer = $this->renderers->get($format, $report);
        } catch (UnsupportedFormatException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($renderer->render($report));

        return self::SUCCESS;
    }

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
}
