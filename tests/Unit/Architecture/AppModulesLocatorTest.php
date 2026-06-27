<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

function locatorFixture(): AppModulesLocator
{
    return new AppModulesLocator(new ModuleRegistry(appModulesFixture()), 'myapp');
}

it('returns all modules as descriptors keyed and sorted by package', function (): void {
    $all = locatorFixture()->all();

    expect(array_keys($all))->toBe(['myapp/amazon', 'myapp/core', 'myapp/kernel', 'myapp/pim', 'myapp/sale'])
        ->and($all['myapp/core'])->toBeInstanceOf(ModuleDescriptor::class)
        ->and($all['myapp/core']->shortName)->toBe('core')
        ->and($all['myapp/core']->version)->toBe('1.0.0')
        ->and($all['myapp/core']->require)->toHaveKey('myapp/kernel');
});

it('marks the core module via isCore()', function (): void {
    $all = locatorFixture()->all();

    expect($all['myapp/core']->isCore())->toBeTrue()
        ->and($all['myapp/sale']->isCore())->toBeFalse();
});

it('locates a module by composer package', function (): void {
    expect(locatorFixture()->byComposerPackage('myapp/sale')?->shortName)->toBe('sale')
        ->and(locatorFixture()->byComposerPackage('myapp/nope'))->toBeNull();
});

it('locates a module by class via psr-4 namespace', function (): void {
    expect(locatorFixture()->byClass('Myapp\\Sale\\Models\\Order')?->name)->toBe('myapp/sale')
        ->and(locatorFixture()->byClass('Other\\Thing'))->toBeNull();
});

it('locates a module by file path', function (): void {
    $path = appModulesFixture().'/pim/src/Models/Product.php';

    expect(locatorFixture()->byPath($path)?->name)->toBe('myapp/pim');
});

it('resolves a bare name by prepending the default vendor', function (): void {
    expect(locatorFixture()->resolve('sale')?->name)->toBe('myapp/sale')
        ->and(locatorFixture()->resolve('amazon')?->name)->toBe('myapp/amazon');
});

it('resolves case-insensitively', function (): void {
    expect(locatorFixture()->resolve('Sale')?->name)->toBe('myapp/sale')
        ->and(locatorFixture()->resolve('SALE')?->name)->toBe('myapp/sale');
});

it('passes a slashed name through resolution unchanged', function (): void {
    expect(locatorFixture()->resolve('myapp/sale')?->name)->toBe('myapp/sale')
        ->and(locatorFixture()->resolve('vendor/nope'))->toBeNull();
});

it('returns null from resolve for an unknown bare name', function (): void {
    expect(locatorFixture()->resolve('nope'))->toBeNull();
});

it('resolveOrFail returns the descriptor for a known module', function (): void {
    expect(locatorFixture()->resolveOrFail('sale')->name)->toBe('myapp/sale');
});

it('resolveOrFail throws a friendly error showing the resolved name', function (): void {
    locatorFixture()->resolveOrFail('amazonx');
})->throws(
    InvalidArgumentException::class,
    "Unknown module: amazonx\nResolved to: myapp/amazonx",
);

it('resolveOrFail omits the resolved line when the input has a slash', function (): void {
    locatorFixture()->resolveOrFail('vendor/nope');
})->throws(InvalidArgumentException::class, 'Unknown module: vendor/nope');
