<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use InvalidArgumentException;

class MakeMigrationCommand extends Command
{
    protected $signature = 'module:make:migration {module : The name of the module} {name : The name of the migration} {--create= : The table to be created} {--table= : The table to migrate}';

    protected $description = 'Create a new migration for a module';

    public function __construct(private readonly ModuleLocator $locator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $module = $this->locator->resolveOrFail((string) $this->argument('module'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $path = $module->path.'/database/migrations';

        $this->runCommand(MigrateMakeCommand::class, [
            '--create' => $this->option('create'),
            '--path' => $path,
            '--realpath' => $path,
            '--table' => $this->option('table'),
            'name' => $this->argument('name'),
        ], $this->output);

        return self::SUCCESS;
    }
}
