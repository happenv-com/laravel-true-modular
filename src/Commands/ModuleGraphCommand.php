<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Analyzer\GraphAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\Concerns\RendersArchitectureReport;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Override;

final class ModuleGraphCommand extends Command
{
    use RendersArchitectureReport;

    #[Override]
    protected $signature = 'module:graph
                            {--root= : Restrict the graph to this module subtree}
                            {--format= : Output format (tree, mermaid, dot, json)}
                            {--schema-version=1 : Machine API schema version}';

    #[Override]
    protected $description = 'Render the module dependency graph (tree, mermaid, dot, json)';

    public function __construct(
        private readonly ArchitectureIndexBuilder $builder,
        private readonly GraphAnalyzer $analyzer,
        protected RendererRegistry $renderers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $root = $this->option('root');
        $root = $root === null ? null : (string) $root;

        try {
            $report = $this->analyzer->analyze($this->builder->build(), $root);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->output($report, 'tree');
    }
}
