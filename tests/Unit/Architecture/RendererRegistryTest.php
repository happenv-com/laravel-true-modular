<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\JsonRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Renderer\UnsupportedFormatException;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;

it('resolves a renderer by format', function (): void {
    $registry = new RendererRegistry([new JsonRenderer]);

    expect($registry->get('json', new ImpactReport('core', [], [])))
        ->toBeInstanceOf(JsonRenderer::class);
});

it('throws for an unknown format', function (): void {
    (new RendererRegistry([new JsonRenderer]))->get('xml', new ImpactReport('core', [], []));
})->throws(UnsupportedFormatException::class);
