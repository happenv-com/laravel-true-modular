<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\JsonRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\RenderContext;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;

it('wraps the report payload with a schema block', function (): void {
    $json = (new JsonRenderer)->render(new ImpactReport('core', ['pim'], ['amazon']), new RenderContext('x'));
    $decoded = json_decode($json, true);

    expect($decoded['schema'])->toBe(['name' => 'impact', 'version' => 1])
        ->and($decoded['module'])->toBe('core')
        ->and($decoded['direct'])->toBe(['pim'])
        ->and($decoded['total'])->toBe(2);
});

it('reports json as its format and supports any report', function (): void {
    $renderer = new JsonRenderer;

    expect($renderer->format())->toBe('json')
        ->and($renderer->supports(new ImpactReport('core', [], [])))->toBeTrue();
});

it('preserves full vendor-prefixed names even when rendering context would shorten them', function (): void {
    $json = (new JsonRenderer)->render(
        new ImpactReport('happenv/core', ['happenv/sale'], ['happenv/pim']),
        new RenderContext('happenv'),
    );
    $decoded = json_decode($json, true);

    expect($decoded['module'])->toBe('happenv/core')
        ->and($decoded['direct'])->toBe(['happenv/sale'])
        ->and($decoded['total'])->toBe(2);
});
