<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;

afterEach(function (): void {
    Application::modulesNamespace(Application::DEFAULT_MODULES_NAMESPACE);
});

it('derives the default vendor from the default namespace', function (): void {
    expect(Application::getModulesVendor())->toBe('true-module');
});

it('derives the vendor from a custom namespace', function (): void {
    Application::modulesNamespace('Happenv');

    expect(Application::getModulesVendor())->toBe('happenv');
});
