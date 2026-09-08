<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModularApplication;
use Illuminate\Foundation\Configuration\ApplicationBuilder;

afterEach(function (): void {
    // Reset process-wide settings so they cannot leak into other tests.
    Application::moduleComposerType(Application::DEFAULT_COMPOSER_TYPE);
    Application::modulesDirectory(Application::DEFAULT_MODULES_DIRECTORY);
    Application::modulesNamespace(Application::DEFAULT_MODULES_NAMESPACE);
    Application::coreModuleName(Application::DEFAULT_CORE_MODULE_NAME);
});

it('applies settings fluently and is chainable', function (): void {
    $modular = new ModularApplication;

    expect($modular->composerType('acme-module'))->toBe($modular)
        ->and($modular->modulesDirectory('packages'))->toBe($modular)
        ->and($modular->modulesNamespace('Acme'))->toBe($modular)
        ->and($modular->coreModuleName('Kernel'))->toBe($modular)
        ->and(Application::getModuleComposerType())->toBe('acme-module')
        ->and(Application::getModulesDirectory())->toBe('packages')
        ->and(Application::getModulesNamespace())->toBe('Acme')
        ->and(Application::getCoreModuleName())->toBe('Kernel');
});

it('hands off to the standard Laravel application builder', function (): void {
    $builder = (new ModularApplication)
        ->composerType('acme-module')
        ->modulesDirectory('packages')
        ->configure(basePath: sys_get_temp_dir());

    expect($builder)->toBeInstanceOf(ApplicationBuilder::class);
});
