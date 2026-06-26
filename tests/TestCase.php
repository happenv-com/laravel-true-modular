<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Tests;

use Happenv\LaravelTrueModular\KernelServiceProvider;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Testbench v11 resolves the application base path via this static method
     * (not getBasePath()). Pointing it at tests/fixtures makes
     * base_path('app-modules') resolve to the fixture modules, so classes that
     * use ModuleRegistry::make()/ModuleFileFinder::make() work in tests.
     */
    public static function applicationBasePath(): string
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
     * Bind ModuleRegistry to the fixtures so tests never depend on a real app.
     *
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app->singleton(
            ModuleRegistry::class,
            static fn (): ModuleRegistry => new ModuleRegistry(__DIR__.'/fixtures/app-modules'),
        );
    }
}
