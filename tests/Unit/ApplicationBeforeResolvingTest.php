<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;

interface BeforeResolvingContractStub {}

final class BeforeResolvingImplementationStub implements BeforeResolvingContractStub {}

final class BeforeResolvingOtherStub {}

it('asks the autoloader about an abstract that is not a class only once', function (): void {
    $app = new Application(dirname(appModulesFixture()));
    $app->instance('beforeResolvingTestBinding', new BeforeResolvingImplementationStub);
    $app->beforeResolving(BeforeResolvingContractStub::class, static fn (): null => null);

    $attempts = 0;
    $autoloader = static function (string $class) use (&$attempts): void {
        if ($class === 'beforeResolvingTestBinding') {
            $attempts++;
        }
    };
    spl_autoload_register($autoloader);

    try {
        $app->make('beforeResolvingTestBinding');
        $app->make('beforeResolvingTestBinding');
        $app->make('beforeResolvingTestBinding');
    } finally {
        spl_autoload_unregister($autoloader);
    }

    expect($attempts)->toBe(1);
});

it('fires callbacks registered for an interface the abstract implements', function (): void {
    $app = new Application(dirname(appModulesFixture()));
    $calls = 0;
    $app->beforeResolving(BeforeResolvingContractStub::class, static function () use (&$calls): void {
        $calls++;
    });

    $app->make(BeforeResolvingImplementationStub::class);
    $app->make(BeforeResolvingImplementationStub::class);
    $app->make(BeforeResolvingOtherStub::class);

    expect($calls)->toBe(2);
});

it('fires a callback registered after the abstract was already resolved', function (): void {
    $app = new Application(dirname(appModulesFixture()));
    $app->beforeResolving(BeforeResolvingOtherStub::class, static fn (): null => null);
    $app->make(BeforeResolvingImplementationStub::class);

    $calls = 0;
    $app->beforeResolving(BeforeResolvingContractStub::class, static function () use (&$calls): void {
        $calls++;
    });
    $app->make(BeforeResolvingImplementationStub::class);

    expect($calls)->toBe(1);
});

it('keeps firing global before resolving callbacks for every resolution', function (): void {
    $app = new Application(dirname(appModulesFixture()));
    $resolved = [];
    $app->beforeResolving(static function (string $abstract) use (&$resolved): void {
        $resolved[] = $abstract;
    });

    $app->make(BeforeResolvingOtherStub::class);
    $app->make(BeforeResolvingOtherStub::class);

    expect($resolved)->toBe([BeforeResolvingOtherStub::class, BeforeResolvingOtherStub::class]);
});
