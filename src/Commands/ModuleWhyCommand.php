<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Analyzer\WhyAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Override;

final class ModuleWhyCommand extends AbstractArchitectureCommand
{
    protected $signature = 'module:why
                            {from : The dependent module}
                            {to : The dependency module}
                            {--format= : Output format (text, json)}
                            {--schema-version=1 : Machine API schema version}';

    protected $description = 'Explain why one module depends on another (shortest dependency path)';

    public function __construct(
        ArchitectureIndexBuilder $builder,
        private readonly WhyAnalyzer $analyzer,
        RendererRegistry $renderers,
    ) {
        parent::__construct($builder, $renderers);
    }

    #[Override]
    protected function buildReport(ArchitectureIndex $index): ArchitectureReport
    {
        return $this->analyzer->analyze(
            $index,
            (string) $this->argument('from'),
            (string) $this->argument('to'),
        );
    }

    #[Override]
    protected function defaultFormat(): string
    {
        return 'text';
    }
}
