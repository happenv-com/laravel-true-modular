<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleName;

it('derives a kebab vendor from a namespace', function (): void {
    expect(ModuleName::vendorFromNamespace('TrueModule'))->toBe('true-module')
        ->and(ModuleName::vendorFromNamespace('Happenv'))->toBe('happenv')
        ->and(ModuleName::vendorFromNamespace('Acme\\Modules'))->toBe('modules');
});

it('qualifies a bare name with the vendor and lowercases it', function (): void {
    expect(ModuleName::qualify('inventory', 'happenv'))->toBe('happenv/inventory')
        ->and(ModuleName::qualify('Inventory', 'happenv'))->toBe('happenv/inventory')
        ->and(ModuleName::qualify('INVENTORY', 'happenv'))->toBe('happenv/inventory');
});

it('passes through a slashed name unchanged except for lowercasing', function (): void {
    expect(ModuleName::qualify('acme/catalog', 'happenv'))->toBe('acme/catalog')
        ->and(ModuleName::qualify('Acme/Catalog', 'happenv'))->toBe('acme/catalog')
        ->and(ModuleName::qualify('filament/actions', 'happenv'))->toBe('filament/actions');
});
