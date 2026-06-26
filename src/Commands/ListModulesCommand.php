<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Report\ModulesReport;
use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Override;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

class ListModulesCommand extends Command
{
    #[Override]
    protected $signature = 'module:list
                            {--reverse : Show in reverse dependency order (dependents first)}
                            {--simple : Show simple list without table}
                            {--format= : Output format (table, json)}';

    #[Override]
    protected $description = 'List all modules in dependency order';

    public function __construct(
        private readonly ModuleTree $moduleTree,
        private readonly ArchitectureIndexBuilder $builder,
        private readonly RendererRegistry $renderers,
    ) {
        parent::__construct();
    }

    /**
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function handle(): int
    {
        try {
            $order = $this->option('reverse')
                ? $this->moduleTree->getReverseTopologicalOrder()
                : $this->moduleTree->getTopologicalOrder();
        } catch (CircularDependencyException $circularDependencyException) {
            $this->error('Circular dependencies detected!');

            foreach ($circularDependencyException->cycles as $cycle) {
                $this->error('  '.implode(' -> ', $cycle));
            }

            return self::FAILURE;
        }

        $index = $this->builder->build();

        if ($this->option('format') === 'json') {
            return $this->renderJson($order, $index);
        }

        return $this->option('simple')
            ? $this->showSimpleList($order)
            : $this->showTable($order, $index);
    }

    /**
     * @param  array<string>  $order
     *
     * @throws InvalidArgumentException
     */
    private function renderJson(array $order, ArchitectureIndex $index): int
    {
        $report = new ModulesReport($this->rows($order, $index), (bool) $this->option('reverse'));

        $this->line($this->renderers->get('json', $report)->render($report));

        return self::SUCCESS;
    }

    /**
     * @param  array<string>  $order
     *
     * @throws InvalidArgumentException
     */
    private function showSimpleList(array $order): int
    {
        $direction = $this->option('reverse') ? 'dependents first' : 'dependencies first';
        $this->components->info(sprintf('Modules in order (%s):', $direction));
        $this->newLine();

        foreach ($order as $index => $moduleName) {
            $this->line(sprintf('  %d. %s', $index + 1, $moduleName));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string>  $order
     *
     * @throws InvalidArgumentException
     */
    private function showTable(array $order, ArchitectureIndex $index): int
    {
        $direction = $this->option('reverse') ? 'dependents first' : 'dependencies first';
        $this->components->info(sprintf('Modules (%s):', $direction));
        $this->newLine();

        $tableData = [];

        foreach ($this->rows($order, $index) as $position => $row) {
            $tableData[] = [
                $position + 1,
                $row['name'],
                $row['dependencies'] !== [] ? implode(PHP_EOL, $row['dependencies']) : '-',
                $row['path'],
            ];

            $tableData[] = ['-', '-', '-', '-'];
        }

        $this->table(
            ['#', 'Module', 'Depends On', 'Path'],
            $tableData
        );

        $this->components->info(sprintf('Total: %d modules', count($order)));

        return self::SUCCESS;
    }

    /**
     * Build ordered rows sourced from the architecture index (dependencies and
     * path), keeping a single description of each module's data.
     *
     * @param  array<string>  $order
     * @return list<array{name: string, dependencies: array<string>, path: string}>
     */
    private function rows(array $order, ArchitectureIndex $index): array
    {
        $rows = [];

        foreach ($order as $moduleName) {
            $descriptor = $index->module($moduleName);

            $rows[] = [
                'name' => $moduleName,
                'dependencies' => $index->graph()->dependencies($moduleName),
                'path' => $descriptor === null ? '' : str_replace(base_path().'/', '', $descriptor->path),
            ];
        }

        return $rows;
    }
}
