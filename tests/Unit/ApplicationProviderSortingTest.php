<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ServiceProviderSorter;
use Happenv\LaravelTrueModular\Tests\Doubles\UnmoduledServiceProvider;
use Illuminate\Support\ServiceProvider;
use Myapp\Kernel\KernelModuleServiceProvider;
use Myapp\Sale\SaleModuleServiceProvider;

/**
 * A modular application over the fixture modules, with three providers registered in
 * an order the sorter has to undo: the module that sorts last, then a provider owned
 * by no module, then the module that sorts first.
 */
$bootFixtureApplication = static function (): Application {
    $app = new Application(dirname(appModulesFixture()));

    $app->register(new SaleModuleServiceProvider($app));
    $app->register(new UnmoduledServiceProvider($app));
    $app->register(new KernelModuleServiceProvider($app));

    $app->boot();

    return $app;
};

it('answers getProvider() after boot, because sorting reorders the array without rekeying it', function () use ($bootFixtureApplication): void {
    // `markAsRegistered()` stores providers under `get_class($provider)` and `getProvider()` is a
    // plain lookup on that key — so anything that hands `$serviceProviders` back as a LIST makes
    // every `getProvider()` call in the application answer null, silently and for all providers,
    // module and framework alike. Assigning the sorter's result in `boot()` used to do exactly that.
    $app = $bootFixtureApplication();

    expect($app->getProvider(SaleModuleServiceProvider::class))->toBeInstanceOf(SaleModuleServiceProvider::class)
        ->and($app->getProvider(KernelModuleServiceProvider::class))->toBeInstanceOf(KernelModuleServiceProvider::class)
        ->and($app->getProvider(UnmoduledServiceProvider::class))->toBeInstanceOf(UnmoduledServiceProvider::class);
});

it('hands back the very instance it booted, not a second one built on the way out', function () use ($bootFixtureApplication): void {
    // `getProvider()` returning *something* is not enough: callers reach for it to talk to the
    // provider that ran, so identity is the property under test.
    $app = $bootFixtureApplication();

    $registered = $app->getProviders(SaleModuleServiceProvider::class);

    expect($app->getProvider(SaleModuleServiceProvider::class))->toBe(reset($registered));
});

it('refuses to register a provider twice after boot, which is what its own getProvider() guard is for', function () use ($bootFixtureApplication): void {
    // The one caller of `getProvider()` inside this package: `register()` opens with
    // `if (($registered = $this->getProvider($provider)) && ! $force)`. With the keys gone that
    // guard could never fire after boot, so a second `register()` built a SECOND instance, filed it
    // under the class name the sort had just vacated, and — because the application is already
    // booted — ran `initialize()` and `boot()` on it. MEASURED: two instances, where the whole
    // point of the guard is one. Everything a provider does on the way up therefore happened twice.
    $app = $bootFixtureApplication();

    $again = $app->register(SaleModuleServiceProvider::class);

    expect($app->getProviders(SaleModuleServiceProvider::class))->toHaveCount(1)
        ->and($again)->toBe($app->getProvider(SaleModuleServiceProvider::class));
});

it('still orders module providers by dependency, behind the providers that belong to no module', function () use ($bootFixtureApplication): void {
    // The guard on the fix above: preserving keys must not cost the ordering the sorter exists for.
    // `myapp/sale` depends on `myapp/kernel` transitively, and it was registered first on purpose.
    //
    // Only the tail is asserted: the constructor registers Laravel's own base providers (events,
    // log, routing) ahead of anything this test hands over, and those are the other half of the
    // claim — they belong to no module, so they sort into the same leading bucket.
    $app = $bootFixtureApplication();

    $booted = array_values(array_map(
        static fn (ServiceProvider $provider): string => $provider::class,
        (fn (): array => $this->serviceProviders)->call($app),
    ));

    expect(array_slice($booted, -3))->toBe([
        UnmoduledServiceProvider::class,
        KernelModuleServiceProvider::class,
        SaleModuleServiceProvider::class,
    ]);
});

it('keys its result by class name, so the result can be assigned back over a class-keyed array', function (): void {
    // Stated at the sorter itself, since `boot()` is only the first caller that depends on it.
    $app = new Application(dirname(appModulesFixture()));

    $sorted = $app->make(ServiceProviderSorter::class)->sort([
        new SaleModuleServiceProvider($app),
        new UnmoduledServiceProvider($app),
        new KernelModuleServiceProvider($app),
    ]);

    expect(array_keys($sorted))->toBe([
        UnmoduledServiceProvider::class,
        KernelModuleServiceProvider::class,
        SaleModuleServiceProvider::class,
    ]);
});
