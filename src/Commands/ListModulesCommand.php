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
use Happenv\LaravelTrueModular\ModuleSystem\ModuleActivation;
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
                            {--format= : Output format (table, text, json)}
                            {--with-vendor : Show full vendor/name even for local modules}
                            {--only-disabled : Show only the modules this environment switches off}';

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
    public function handle(ModuleActivation $activation): int
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

        $context = new RenderContext(Application::getModulesVendor(), (bool) $this->option('with-vendor'));

        // The boxed table is an interactive console view (Symfony table component),
        // so it stays here; every string format flows through the renderer registry.
        if ($format === 'table') {
            return $this->showTable($this->rows($order, $index, $activation), $context);
        }
        $report = new ModulesReport($this->rows($order, $index, $activation), (bool) $this->option('reverse'));
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
     * @param  list<array{name: string, dependencies: array<string>, path: string, enabled: bool}>  $rows
     *
     * @throws InvalidArgumentException
     */
    private function showTable(array $rows, RenderContext $context): int
    {
        $direction = $this->option('reverse') ? 'dependents first' : 'dependencies first';
        $this->components->info(sprintf('Modules (%s):', $direction));
        $this->newLine();

        $tableData = [];

        foreach ($rows as $position => $row) {
            $dependencies = array_map(
                static fn (string $dependency): string => $context->display($dependency),
                $row['dependencies'],
            );

            $tableData[] = [
                $position + 1,
                $context->display($row['name']),
                $row['enabled'] ? 'enabled' : 'disabled',
                $dependencies !== [] ? implode(PHP_EOL, $dependencies) : '-',
                $row['path'],
            ];

            $tableData[] = ['-', '-', '-', '-', '-'];
        }

        $this->table(['#', 'Module', 'Status', 'Depends On', 'Path'], $tableData);

        $this->components->info(sprintf('Total: %d modules', count($rows)));

        return self::SUCCESS;
    }

    /**
     * Build ordered rows sourced from the architecture index (dependencies and
     * path) and the activation state, keeping a single description of each
     * module's data.
     *
     * @param  array<string>  $order
     * @return list<array{name: string, dependencies: array<string>, path: string, enabled: bool}>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function rows(array $order, ArchitectureIndex $index, ModuleActivation $activation): array
    {
        $onlyDisabled = (bool) $this->option('only-disabled');
        $rows = [];

        foreach ($order as $moduleName) {
            $enabled = ! $activation->isDisabled($moduleName);

            if ($onlyDisabled && $enabled) {
                continue;
            }

            $descriptor = $index->module($moduleName);

            $rows[] = [
                'name' => $moduleName,
                'dependencies' => $index->graph()->dependencies($moduleName),
                'path' => $descriptor instanceof ModuleDescriptor ? str_replace(base_path().'/', '', $descriptor->path) : '',
                'enabled' => $enabled,
            ];
        }

        return $rows;
    }
}
