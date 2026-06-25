<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Analyzer\ImpactAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\Concerns\RendersArchitectureReport;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Override;

final class ModuleImpactCommand extends Command
{
    use RendersArchitectureReport;

    #[Override]
    protected $signature = 'module:impact
                            {module : The module to analyze}
                            {--format= : Output format (text, json)}
                            {--schema-version=1 : Machine API schema version}';

    #[Override]
    protected $description = 'Show which modules are affected by a change to the given module';

    public function __construct(
        private readonly ArchitectureIndexBuilder $builder,
        private readonly ImpactAnalyzer $analyzer,
        protected RendererRegistry $renderers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = (string) $this->argument('module');

        try {
            $report = $this->analyzer->analyze($this->builder->build(), $module);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->output($report, 'text');
    }
}
