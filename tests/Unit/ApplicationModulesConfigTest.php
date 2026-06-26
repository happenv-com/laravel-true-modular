<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;

afterEach(function (): void {
    // Reset the process-wide setting so it cannot leak into other tests.
    Application::modulesDirectory(ModuleTree::DEFAULT_DIRECTORY);
});

it('defaults the modules directory to the module tree default', function (): void {
    expect(Application::getModulesDirectory())->toBe(ModuleTree::DEFAULT_DIRECTORY)
        ->and(ModuleTree::DEFAULT_DIRECTORY)->toBe('app-modules');
});

it('configures the modules directory and is chainable like moduleComposerType', function (): void {
    expect(Application::modulesDirectory('packages'))->toBe(Application::class)
        ->and(Application::getModulesDirectory())->toBe('packages');
});
