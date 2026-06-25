<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Analyzer\WhyAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\Concerns\RendersArchitectureReport;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Override;

final class ModuleWhyCommand extends Command
{
    use RendersArchitectureReport;

    #[Override]
    protected $signature = 'module:why
                            {from : The dependent module}
                            {to : The dependency module}
                            {--format= : Output format (text, json)}
                            {--schema-version=1 : Machine API schema version}';

    #[Override]
    protected $description = 'Explain why one module depends on another (shortest dependency path)';

    public function __construct(
        private readonly ArchitectureIndexBuilder $builder,
        private readonly WhyAnalyzer $analyzer,
        protected RendererRegistry $renderers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = (string) $this->argument('from');
        $to = (string) $this->argument('to');

        try {
            $report = $this->analyzer->analyze($this->builder->build(), $from, $to);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->output($report, 'text');
    }
}
