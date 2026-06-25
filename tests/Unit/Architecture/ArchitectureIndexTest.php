<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ModuleQuery;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function descriptor(string $name, array $require = [], bool $core = false): ModuleDescriptor
{
    return new ModuleDescriptor(
        name: $name,
        shortName: substr($name, (int) strrpos($name, '/') + 1),
        path: '/app-modules/'.substr($name, (int) strrpos($name, '/') + 1),
        namespace: null,
        version: null,
        provider: null,
        require: $require,
        isCore: $core,
    );
}

function indexFixture(): ArchitectureIndex
{
    $modules = [
        'myapp/core' => descriptor('myapp/core', core: true),
        'myapp/pim' => descriptor('myapp/pim', ['myapp/core' => '*']),
        'myapp/sale' => descriptor('myapp/sale', ['myapp/pim' => '*']),
    ];

    $graph = new DependencyGraph([
        'myapp/core' => [],
        'myapp/pim' => ['myapp/core'],
        'myapp/sale' => ['myapp/pim'],
    ]);

    return new ArchitectureIndex($modules, $graph);
}

it('looks up a module and reports presence', function (): void {
    expect(indexFixture()->module('myapp/pim')?->name)->toBe('myapp/pim')
        ->and(indexFixture()->has('myapp/nope'))->toBeFalse();
});

it('returns a ModuleQuery from modules()', function (): void {
    expect(indexFixture()->modules())->toBeInstanceOf(ModuleQuery::class)
        ->and(indexFixture()->modules()->names())
        ->toBe(['myapp/core', 'myapp/pim', 'myapp/sale']);
});

it('queries modules depending on a module', function (): void {
    expect(indexFixture()->modules()->dependingOn('myapp/core')->names())
        ->toBe(['myapp/pim']);
});

it('sorts a query by dependency depth', function (): void {
    expect(indexFixture()->modules()->sortedByDepth()->names())
        ->toBe(['myapp/core', 'myapp/pim', 'myapp/sale']);
});

it('serializes to a deterministic array', function (): void {
    $array = indexFixture()->toArray();

    expect($array)->toHaveKeys(['modules', 'dependencies'])
        ->and(array_keys($array['dependencies']))->toBe(['myapp/core', 'myapp/pim', 'myapp/sale'])
        ->and($array['dependencies']['myapp/sale'])->toBe(['myapp/pim']);
});
