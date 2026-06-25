<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Tests;

use Happenv\LaravelTrueModular\KernelServiceProvider;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getBasePath(): string
    {
        return __DIR__.'/fixtures';
    }

    /**
     * @param  Application  $app
     * @return array<int,class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [KernelServiceProvider::class];
    }

    /**
     * Bind ModuleTree to the fixtures so tests never depend on a real app.
     *
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app->singleton(
            ModuleTree::class,
            static fn (): ModuleTree => new ModuleTree(__DIR__.'/fixtures/app-modules'),
        );
    }
}
