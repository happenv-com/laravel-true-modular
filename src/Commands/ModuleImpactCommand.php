<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Analyzer\ImpactAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Override;

final class ModuleImpactCommand extends AbstractArchitectureCommand
{
    #[Override]
    protected $signature = 'module:impact
                            {module : The module to analyze}
                            {--format= : Output format (text, json)}
                            {--schema-version=1 : Machine API schema version}';

    #[Override]
    protected $description = 'Show which modules are affected by a change to the given module';

    public function __construct(
        ArchitectureIndexBuilder $builder,
        private readonly ImpactAnalyzer $analyzer,
        RendererRegistry $renderers,
    ) {
        parent::__construct($builder, $renderers);
    }

    #[Override]
    protected function buildReport(ArchitectureIndex $index): ArchitectureReport
    {
        return $this->analyzer->analyze($index, (string) $this->argument('module'));
    }

    #[Override]
    protected function defaultFormat(): string
    {
        return 'text';
    }
}
