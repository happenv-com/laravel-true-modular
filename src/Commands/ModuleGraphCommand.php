<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Analyzer\GraphAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Override;

final class ModuleGraphCommand extends AbstractArchitectureCommand
{
    protected $signature = 'module:graph
                            {--root= : Restrict the graph to this module subtree}
                            {--format= : Output format (tree, mermaid, dot, json)}
                            {--schema-version=1 : Machine API schema version}';

    protected $description = 'Render the module dependency graph (tree, mermaid, dot, json)';

    public function __construct(
        ArchitectureIndexBuilder $builder,
        private readonly GraphAnalyzer $analyzer,
        RendererRegistry $renderers,
        private readonly ModuleLocator $locator,
    ) {
        parent::__construct($builder, $renderers);
    }

    #[Override]
    protected function buildReport(ArchitectureIndex $index): ArchitectureReport
    {
        $root = $this->option('root');

        if ($root !== null) {
            $root = $this->locator->resolveOrFail((string) $root)->name;
        }

        return $this->analyzer->analyze($index, $root);
    }

    #[Override]
    protected function defaultFormat(): string
    {
        return 'tree';
    }
}
