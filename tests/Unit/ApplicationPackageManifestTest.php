<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleAwarePackageManifest;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Foundation\PackageManifest;

it('binds the module-aware package manifest so disabled modules never reach discovery', function (): void {
    $app = new Application(dirname(appModulesFixture()));

    expect($app->make(PackageManifest::class))->toBeInstanceOf(ModuleAwarePackageManifest::class);
});

it('shares one module registry instance across the application', function (): void {
    $app = new Application(dirname(appModulesFixture()));

    expect($app->make(ModuleRegistry::class))->toBe($app->make(ModuleRegistry::class));
});
