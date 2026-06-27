<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\DotRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\MermaidRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\RenderContext;
use Happenv\LaravelTrueModular\Architecture\Renderer\TreeRenderer;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;

function graphReport(): GraphReport
{
    return new GraphReport(
        roots: ['core'],
        dependents: [
            'core' => ['pim'],
            'pim' => ['sale'],
            'sale' => [],
        ],
        root: null,
    );
}

it('renders an ascii tree', function (): void {
    $text = (new TreeRenderer)->render(graphReport(), new RenderContext('x'));

    expect($text)->toContain('core')
        ->and($text)->toContain('└── pim')
        ->and($text)->toContain('└── sale');
});

it('renders mermaid edges', function (): void {
    $text = (new MermaidRenderer)->render(graphReport(), new RenderContext('x'));

    expect($text)->toContain('graph TD')
        ->and($text)->toContain('core --> pim')
        ->and($text)->toContain('pim --> sale');
});

it('renders graphviz dot edges', function (): void {
    $text = (new DotRenderer)->render(graphReport(), new RenderContext('x'));

    expect($text)->toContain('digraph')
        ->and($text)->toContain('"core" -> "pim"');
});

it('only supports graph reports', function (): void {
    $impact = new ImpactReport('core', [], []);

    expect((new TreeRenderer)->supports($impact))->toBeFalse()
        ->and((new MermaidRenderer)->supports(graphReport()))->toBeTrue();
});

it('tree renderer handles cyclic dependencies without hanging', function (): void {
    $report = new GraphReport(
        roots: ['a'],
        dependents: ['a' => ['b'], 'b' => ['a']],
        root: null,
    );

    $text = (new TreeRenderer)->render($report, new RenderContext('x'));

    expect($text)->toContain('a')
        ->and($text)->toContain('b');
});

it('mermaid shortens default-vendor nodes and keeps external full', function (): void {
    $report = new GraphReport(
        roots: ['happenv/core'],
        dependents: ['happenv/core' => ['happenv/product', 'acme/catalog'], 'happenv/product' => [], 'acme/catalog' => []],
        root: null,
    );

    $out = (new MermaidRenderer)->render($report, new RenderContext('happenv'));

    expect($out)->toContain('core --> product')
        ->and($out)->toContain('core --> acme/catalog')
        ->and($out)->not->toContain('happenv/core');
});
