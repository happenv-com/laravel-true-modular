<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\RenderContext;

it('shows the short name for a default-vendor module', function (): void {
    expect((new RenderContext('happenv'))->display('happenv/inventory'))->toBe('inventory');
});

it('shows the full name for an external module', function (): void {
    expect((new RenderContext('happenv'))->display('acme/catalog'))->toBe('acme/catalog');
});

it('shows full names for everything when withVendor is true', function (): void {
    expect((new RenderContext('happenv', true))->display('happenv/inventory'))->toBe('happenv/inventory')
        ->and((new RenderContext('happenv', true))->display('acme/catalog'))->toBe('acme/catalog');
});

it('returns a name without a slash unchanged', function (): void {
    expect((new RenderContext('happenv'))->display('core'))->toBe('core');
});
