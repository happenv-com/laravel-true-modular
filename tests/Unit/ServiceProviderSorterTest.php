<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Happenv\LaravelTrueModular\ServiceProviderSorter;
use Happenv\LaravelTrueModular\Tests\Doubles\UnmoduledServiceProvider;
use Illuminate\Support\ServiceProvider;
use Myapp\Kernel\KernelModuleServiceProvider;
use Myapp\Sale\SaleModuleServiceProvider;

/**
 * The fixture modules as the module cache would hand them over: with a signature, so the sorter
 * may keep the order it puts providers in. Each call signs afresh unless told otherwise, so tests
 * do not share kept orders through the sorter's process-wide memory.
 *
 * @param  array<string>|null  $topologicalOrder
 */
function signedFixtureModules(?string $signature = null, ?array $topologicalOrder = null): ModuleRegistry
{
    return new ModuleRegistry(appModulesFixture(), topologicalOrder: $topologicalOrder, signature: $signature ?? 'signature-'.bin2hex(random_bytes(8)));
}

/**
 * @param  array<class-string<ServiceProvider>, ServiceProvider>  $sorted
 * @return list<class-string<ServiceProvider>>
 */
function sortedClasses(array $sorted): array
{
    return array_keys($sorted);
}

$app = new Application(dirname(appModulesFixture()));

it('puts a list it has sorted before in the same order, handing back this call\'s instances', function () use ($app): void {
    $modules = signedFixtureModules();
    $first = (new ServiceProviderSorter($modules))->sort([
        new SaleModuleServiceProvider($app), new UnmoduledServiceProvider($app), new KernelModuleServiceProvider($app),
    ]);

    $sale = new SaleModuleServiceProvider($app);
    $again = (new ServiceProviderSorter($modules))->sort([
        $sale, new UnmoduledServiceProvider($app), new KernelModuleServiceProvider($app),
    ]);

    expect(sortedClasses($again))->toBe(sortedClasses($first))
        ->and(sortedClasses($again))->toBe([UnmoduledServiceProvider::class, KernelModuleServiceProvider::class, SaleModuleServiceProvider::class])
        ->and($again[SaleModuleServiceProvider::class])->toBe($sale);
});

it('sorts afresh a list other than the one it kept an order for', function () use ($app): void {
    $modules = signedFixtureModules();
    (new ServiceProviderSorter($modules))->sort([new SaleModuleServiceProvider($app), new KernelModuleServiceProvider($app)]);

    $sorted = (new ServiceProviderSorter($modules))->sort([
        new SaleModuleServiceProvider($app), new KernelModuleServiceProvider($app), new UnmoduledServiceProvider($app),
    ]);

    expect(sortedClasses($sorted))->toBe([UnmoduledServiceProvider::class, KernelModuleServiceProvider::class, SaleModuleServiceProvider::class]);
});

it('keeps the last instance of a class passed twice, from a kept order as from a fresh sort', function () use ($app): void {
    $modules = signedFixtureModules();
    $sorter = new ServiceProviderSorter($modules);
    $firstSale = new SaleModuleServiceProvider($app);
    $lastSale = new SaleModuleServiceProvider($app);
    $providers = [$firstSale, new KernelModuleServiceProvider($app), $lastSale];

    $fresh = $sorter->sort($providers);
    $kept = $sorter->sort($providers);

    expect(sortedClasses($kept))->toBe(sortedClasses($fresh))
        ->and($fresh[SaleModuleServiceProvider::class])->toBe($lastSale)
        ->and($kept[SaleModuleServiceProvider::class])->toBe($lastSale);
});

it('keeps an order under the modules\' signature, which is what makes the signature a promise', function () use ($app): void {
    // Two registries that sign themselves alike share a kept order, even though the second one
    // orders its modules the other way round: the signature is taken at its word. That is why only
    // the module cache — which signs the modules together with their order — supplies one.
    $signature = 'signature-'.bin2hex(random_bytes(8));
    $providers = static fn (): array => [new KernelModuleServiceProvider($app), new SaleModuleServiceProvider($app)];

    $first = (new ServiceProviderSorter(signedFixtureModules($signature)))->sort($providers());
    $second = (new ServiceProviderSorter(signedFixtureModules($signature, ['myapp/sale', 'myapp/pim', 'myapp/core', 'myapp/kernel', 'myapp/amazon'])))->sort($providers());

    expect(sortedClasses($second))->toBe(sortedClasses($first));
});

it('keeps nothing for modules no cache vouches for', function () use ($app): void {
    // Unsigned modules — a scan — are sorted afresh every time, so the order they arrive at is
    // their own: here the same two providers come back in two different orders.
    $providers = static fn (): array => [new KernelModuleServiceProvider($app), new SaleModuleServiceProvider($app)];

    $dependencyOrder = (new ServiceProviderSorter(new ModuleRegistry(appModulesFixture())))->sort($providers());
    $reversed = (new ServiceProviderSorter(new ModuleRegistry(appModulesFixture(), topologicalOrder: ['myapp/sale', 'myapp/pim', 'myapp/core', 'myapp/kernel', 'myapp/amazon'])))->sort($providers());

    expect(sortedClasses($dependencyOrder))->toBe([KernelModuleServiceProvider::class, SaleModuleServiceProvider::class])
        ->and(sortedClasses($reversed))->toBe([SaleModuleServiceProvider::class, KernelModuleServiceProvider::class]);
});
