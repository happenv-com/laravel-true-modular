<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;

afterEach(function (): void {
    // Reset process-wide settings so they cannot leak into other tests.
    Application::modulesDirectory(Application::DEFAULT_MODULES_DIRECTORY);
    Application::modulesNamespace(Application::DEFAULT_MODULES_NAMESPACE);
});

it('defaults the modules directory to the application default', function (): void {
    expect(Application::getModulesDirectory())->toBe(Application::DEFAULT_MODULES_DIRECTORY)
        ->and(Application::DEFAULT_MODULES_DIRECTORY)->toBe('app-modules');
});

it('configures the modules directory', function (): void {
    Application::modulesDirectory('packages');

    expect(Application::getModulesDirectory())->toBe('packages');
});

it('defaults and configures the modules namespace, trimming slashes', function (): void {
    expect(Application::getModulesNamespace())->toBe('TrueModule');

    Application::modulesNamespace('Acme\\');

    expect(Application::getModulesNamespace())->toBe('Acme');
});
