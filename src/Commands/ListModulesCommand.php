<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;
use Happenv\LaravelTrueModular\Architecture\Renderer\RenderContext;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Report\ModulesReport;
use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

class ListModulesCommand extends Command
{
    protected $signature = 'module:list
                            {--reverse : Show in reverse dependency order (dependents first)}
                            {--simple : Show simple list without table}
                            {--format= : Output format (table, text, json)}';

    protected $description = 'List all modules in dependency order';

    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
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
                ? $this->moduleRegistry->getReverseTopologicalOrder()
                : $this->moduleRegistry->getTopologicalOrder();
        } catch (CircularDependencyException $circularDependencyException) {
            $this->error('Circular dependencies detected!');

            foreach ($circularDependencyException->cycles as $cycle) {
                $this->error('  '.implode(' -> ', $cycle));
            }

            return self::FAILURE;
        }

        $index = $this->builder->build();

        $format = $this->resolveFormat();

        $context = new RenderContext(Application::getModulesVendor());

        // The boxed table is an interactive console view (Symfony table component),
        // so it stays here; every string format flows through the renderer registry.
        if ($format === 'table') {
            return $this->showTable($order, $index, $context);
        }
        $report = new ModulesReport($this->rows($order, $index), (bool) $this->option('reverse'));
        $rendered = $this->renderers->get($format, $report)->render($report, $context);

        foreach (explode("\n", $rendered) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    private function resolveFormat(): string
    {
        if ($this->option('simple')) {
            return 'text';
        }

        $format = $this->option('format');

        return is_string($format) && $format !== '' ? $format : 'table';
    }

    /**
     * @param  array<string>  $order
     *
     * @throws InvalidArgumentException
     */
    private function showTable(array $order, ArchitectureIndex $index, RenderContext $context): int
    {
        $direction = $this->option('reverse') ? 'dependents first' : 'dependencies first';
        $this->components->info(sprintf('Modules (%s):', $direction));
        $this->newLine();

        $tableData = [];

        foreach ($this->rows($order, $index) as $position => $row) {
            $dependencies = array_map(
                static fn (string $dependency): string => $context->display($dependency),
                $row['dependencies'],
            );

            $tableData[] = [
                $position + 1,
                $context->display($row['name']),
                $dependencies !== [] ? implode(PHP_EOL, $dependencies) : '-',
                $row['path'],
            ];

            $tableData[] = ['-', '-', '-', '-'];
        }

        $this->table(['#', 'Module', 'Depends On', 'Path'], $tableData);

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
                'path' => $descriptor instanceof ModuleDescriptor ? str_replace(base_path().'/', '', $descriptor->path) : '',
            ];
        }

        return $rows;
    }
}
