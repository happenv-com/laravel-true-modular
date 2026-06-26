<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModularApplication;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Foundation\Configuration\ApplicationBuilder;

afterEach(function (): void {
    // Reset process-wide settings so they cannot leak into other tests.
    Application::moduleComposerType('true-module');
    Application::modulesDirectory(ModuleTree::DEFAULT_DIRECTORY);
});

it('applies settings fluently and is chainable', function (): void {
    $modular = new ModularApplication;

    expect($modular->composerType('acme-module'))->toBe($modular)
        ->and($modular->modulesDirectory('packages'))->toBe($modular)
        ->and(Application::getModuleComposerType())->toBe('acme-module')
        ->and(Application::getModulesDirectory())->toBe('packages');
});

it('hands off to the standard Laravel application builder', function (): void {
    $builder = (new ModularApplication)
        ->composerType('acme-module')
        ->modulesDirectory('packages')
        ->configure(basePath: sys_get_temp_dir());

    expect($builder)->toBeInstanceOf(ApplicationBuilder::class);
});
