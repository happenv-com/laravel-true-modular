<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Override;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

class ListModulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #[Override]
    protected $signature = 'modules:list
                            {--reverse : Show in reverse dependency order (dependents first)}
                            {--simple : Show simple list without table}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override]
    protected $description = 'List all modules in dependency order';

    public function __construct(
        private readonly ModuleTree $moduleTree,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
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

        if ($this->option('simple')) {
            return $this->showSimpleList($order);
        }

        return $this->showTable($order);
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
    /**
     * @param  array<string>  $order
     *
     * @throws FilesystemException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function showTable(array $order): int
    {
        $direction = $this->option('reverse') ? 'dependents first' : 'dependencies first';
        $this->components->info(sprintf('Modules (%s):', $direction));
        $this->newLine();

        $tableData = [];

        foreach ($order as $index => $moduleName) {
            $dependencies = $this->moduleTree->getDependencies($moduleName);
            $path = $this->moduleTree->getModulePath($moduleName);
            $relativePath = str_replace(base_path().'/', '', $path);

            $tableData[] = [
                $index + 1,
                $moduleName,
                $dependencies !== [] ? implode(', ', $dependencies) : '-',
                $relativePath,
            ];
        }

        $this->table(
            ['#', 'Module', 'Depends On', 'Path'],
            $tableData
        );

        $this->components->info(sprintf('Total: %d modules', count($order)));

        return self::SUCCESS;
    }
}
