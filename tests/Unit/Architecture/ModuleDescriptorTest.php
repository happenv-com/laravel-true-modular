<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function descriptorFixture(bool $core = false): ModuleDescriptor
{
    return new ModuleDescriptor(
        name: 'myapp/core',
        shortName: 'core',
        path: '/app-modules/core',
        namespace: 'Myapp\\Core\\',
        version: '1.0.0',
        provider: null,
        require: ['myapp/kernel' => '*'],
        isCore: $core,
    );
}

it('exposes immutable properties', function (): void {
    $descriptor = descriptorFixture();

    expect($descriptor->name)->toBe('myapp/core')
        ->and($descriptor->shortName)->toBe('core')
        ->and($descriptor->version)->toBe('1.0.0');
});

it('reports core status via isCore()', function (): void {
    expect(descriptorFixture(core: true)->isCore())->toBeTrue()
        ->and(descriptorFixture(core: false)->isCore())->toBeFalse();
});

it('serializes to array with stable keys', function (): void {
    expect(descriptorFixture()->toArray())->toBe([
        'name' => 'myapp/core',
        'shortName' => 'core',
        'path' => '/app-modules/core',
        'namespace' => 'Myapp\\Core\\',
        'version' => '1.0.0',
        'provider' => null,
        'require' => ['myapp/kernel' => '*'],
        'isCore' => false,
    ]);
});
