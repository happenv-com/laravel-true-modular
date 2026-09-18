<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistryCache;
use Illuminate\Support\ServiceProvider;

afterEach(function (): void {
    ModuleRegistryCache::make()->clear();
});

it('writes the module registry cache', function (): void {
    $this->artisan('true-modular:cache')
        ->expectsOutputToContain('Module registry cached successfully (5 modules).')
        ->assertSuccessful();

    $modulesPath = base_path(Application::getModulesDirectory());

    expect(ModuleRegistryCache::make()->modules($modulesPath))
        ->toBe((new ModuleRegistry($modulesPath))->getAllModules());
});

it('removes the module registry cache', function (): void {
    $this->artisan('true-modular:cache')->assertSuccessful();

    $this->artisan('true-modular:clear')
        ->expectsOutputToContain('Module registry cache cleared successfully.')
        ->assertSuccessful();

    expect(ModuleRegistryCache::make()->path())->not->toBeFile();
});

it('is written by optimize and removed by optimize:clear', function (): void {
    expect(ServiceProvider::$optimizeCommands)->toHaveKey('true-modular', 'true-modular:cache')
        ->and(ServiceProvider::$optimizeClearCommands)->toHaveKey('true-modular', 'true-modular:clear');
});
