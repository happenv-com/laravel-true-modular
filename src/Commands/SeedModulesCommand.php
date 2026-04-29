<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModularCommands;

use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleFileFinder;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use InvalidArgumentException;
use Override;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

class SeedModulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #[Override]
    protected $signature = 'modules:seed
                            {--module= : Seed only a specific module (e.g., myapp/sale)}
                            {--class= : Seed only a specific seeder class}
                            {--show-order : Show the execution order without seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override]
    protected $description = 'Run module seeders in dependency order (dependencies first)';

    public function __construct(
        private readonly ModuleTree $moduleTree,
        private readonly ModuleFileFinder $fileFinder,
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
            $order = $this->moduleTree->getTopologicalOrder();
        } catch (CircularDependencyException $circularDependencyException) {
            $this->error('Cannot seed modules: circular dependencies detected!');

            foreach ($circularDependencyException->cycles as $cycle) {
                $this->error('  ' . implode(' -> ', $cycle));
            }

            return self::FAILURE;
        }

        if ($this->option('show-order')) {
            return $this->showOrder($order);
        }

        $specificModule = $this->option('module');
        $specificClass = $this->option('class');

        if ($specificModule !== null) {
            return $this->seedModule($specificModule, $specificClass);
        }

        return $this->seedAllModules($specificClass);
    }

    /**
     * @param  array<string>  $order
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function showOrder(array $order): int
    {
        $this->components->info('Module execution order (dependencies first):');
        $this->newLine();

        $seedersGrouped = $this->fileFinder->findFilesGroupedByModule('database/seeders');

        $tableData = [];

        foreach ($order as $index => $moduleName) {
            $dependencies = $this->moduleTree->getDependencies($moduleName);
            $seederCount = count($seedersGrouped->get($moduleName, []));

            $tableData[] = [
                $index + 1,
                $moduleName,
                $dependencies !== [] ? implode(', ', $dependencies) : '-',
                $seederCount > 0 ? $seederCount . ' seeders' : '-',
            ];
        }

        $this->table(
            ['#', 'Module', 'Depends On', 'Seeders'],
            $tableData
        );

        return self::SUCCESS;
    }

    /**
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function seedModule(string $moduleName, ?string $specificClass): int
    {
        $seeders = $this->fileFinder
            ->findClasses('database/seeders', 'Database\Seeders')
            ->filter(fn (array $item): bool => $item['module'] === $moduleName)
            ->pluck('class')
            ->toArray();

        if ($seeders === []) {
            $this->warn('No seeders found for module: ' . $moduleName);

            return self::SUCCESS;
        }

        if ($specificClass !== null) {
            if (! in_array($specificClass, $seeders, true)) {
                $this->error(sprintf("Seeder class '%s' not found in module '%s'", $specificClass, $moduleName));

                return self::FAILURE;
            }

            $seeders = [$specificClass];
        }

        $this->components->info('Seeding module: ' . $moduleName);

        foreach ($seeders as $seederClass) {
            $this->runSeeder($seederClass);
        }

        return self::SUCCESS;
    }

    /**
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function seedAllModules(?string $specificClass): int
    {
        $allSeeders = $this->fileFinder->findClasses('database/seeders', 'Database\Seeders');

        if ($specificClass !== null) {
            $allSeeders = $allSeeders->filter(
                fn (array $item): bool => $item['class'] === $specificClass
            );
        }

        if ($allSeeders->isEmpty()) {
            $this->warn('No seeders found.');

            return self::SUCCESS;
        }

        $totalSeeders = 0;
        $currentModule = null;

        foreach ($allSeeders as $item) {
            if ($currentModule !== $item['module']) {
                if ($currentModule !== null) {
                    $this->newLine();
                }

                $currentModule = $item['module'];
                $this->info('Seeding module: ' . $currentModule);
            }

            $this->runSeeder($item['class']);
            $totalSeeders++;
        }

        $this->newLine();
        $this->components->info(sprintf('Seeding complete: %d seeders.', $totalSeeders));

        return self::SUCCESS;
    }

    /**
     * @param  class-string  $seederClass
     *
     * @throws InvalidArgumentException
     */
    private function runSeeder(string $seederClass): void
    {
        $this->components->task(
            $this->getSeederDisplayName($seederClass),
            static function () use ($seederClass): void {
                /** @var Seeder $seeder */
                $seeder = resolve($seederClass);
                $seeder->__invoke();
            }
        );
    }

    /**
     * @param  class-string  $seederClass
     */
    private function getSeederDisplayName(string $seederClass): string
    {
        $parts = explode('\\', $seederClass);

        return end($parts);
    }
}
