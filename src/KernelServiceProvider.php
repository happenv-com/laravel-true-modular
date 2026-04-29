<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular;

use Happenv\LaravelTrueModular\Commands\ListModulesCommand;
use Happenv\LaravelTrueModular\Commands\MakeMigrationCommand;
use Happenv\LaravelTrueModular\Commands\SeedModulesCommand;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleFileFinder;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Support\ServiceProvider;
use Override;

class KernelServiceProvider extends ServiceProvider
{
    public function initialize(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListModulesCommand::class,
                SeedModulesCommand::class,
                MakeMigrationCommand::class,
            ]);
        }
    }

    #[Override]
    public function register(): void
    {
        $this->app->singleton(ModuleTree::class, static fn (): ModuleTree => ModuleTree::make());

        $this->app->singleton(ModuleFileFinder::class, static fn ($app): ModuleFileFinder => new ModuleFileFinder(
            $app->make(ModuleTree::class)
        ));
    }
}
