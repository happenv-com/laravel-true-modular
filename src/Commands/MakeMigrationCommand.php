<?php

namespace Happenv\LaravelTrueModular\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Override;

use function Happenv\LaravelTrueModular\module_path;

class MakeMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #[Override]
    protected $signature = 'module:make:migration {module : The name of the module} {name : The name of the migration} {--create= : The table to be created} {--table= : The table to migrate}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override]
    protected $description = 'Create a new migration for a module';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->runCommand(MigrateMakeCommand::class, [
            '--create' => $this->option('create'),
            '--path' => $this->getMigrationPath(),
            '--realpath' => $this->getMigrationPath(),
            '--table' => $this->option('table'),
            'name' => $this->argument('name'),
        ], $this->output);
    }

    protected function getMigrationPath(): string
    {
        $module = $this->argument('module');

        return module_path($module, 'database/migrations');
    }
}
