<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\GraphTextRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\ImpactTextRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\ModulesTextRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\RenderContext;
use Happenv\LaravelTrueModular\Architecture\Renderer\WhyTextRenderer;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;
use Happenv\LaravelTrueModular\Architecture\Report\ModulesReport;
use Happenv\LaravelTrueModular\Architecture\Report\WhyReport;

it('renders an impact report as text with sections', function (): void {
    $text = (new ImpactTextRenderer)->render(new ImpactReport('core', ['pim', 'sale'], ['amazon']), new RenderContext('x'));

    expect($text)->toContain('core')
        ->and($text)->toContain('Direct:')
        ->and($text)->toContain('pim')
        ->and($text)->toContain('Indirect:')
        ->and($text)->toContain('amazon')
        ->and($text)->toContain('Total affected: 3');
});

it('renders a why path as an arrow chain', function (): void {
    $text = (new WhyTextRenderer)->render(new WhyReport('amazon', 'core', ['amazon', 'sale', 'core']), new RenderContext('x'));

    expect($text)->toContain('amazon')
        ->and($text)->toContain('sale')
        ->and($text)->toContain('core');
});

it('renders a message when no path exists', function (): void {
    $text = (new WhyTextRenderer)->render(new WhyReport('core', 'amazon', null), new RenderContext('x'));

    expect($text)->toContain('no dependency path');
});

it('renders a graph report as an indented tree', function (): void {
    $report = new GraphReport(
        roots: ['core'],
        dependents: ['core' => ['pim'], 'pim' => ['sale'], 'sale' => []],
        root: null,
    );

    $text = (new GraphTextRenderer)->render($report, new RenderContext('x'));

    expect($text)->toContain('core')
        ->and($text)->toContain('pim')
        ->and($text)->toContain('sale');
});

it('renders a cyclic graph report without hanging', function (): void {
    $report = new GraphReport(
        roots: ['a'],
        dependents: ['a' => ['b'], 'b' => ['a']],
        root: null,
    );

    $text = (new GraphTextRenderer)->render($report, new RenderContext('x'));

    expect($text)->toBeString()
        ->and($text)->toContain('a')
        ->and($text)->toContain('b');
});

it('impact text shows short for default vendor and full for external', function (): void {
    $ctx = new RenderContext('happenv');
    $text = (new ImpactTextRenderer)->render(
        new ImpactReport('happenv/core', ['happenv/sale', 'acme/catalog'], []),
        $ctx,
    );

    expect($text)->toContain('core')->not->toContain('happenv/core')
        ->and($text)->toContain('sale')->not->toContain('happenv/sale')
        ->and($text)->toContain('acme/catalog');
});

it('impact text shows full names under withVendor', function (): void {
    $text = (new ImpactTextRenderer)->render(
        new ImpactReport('happenv/core', ['happenv/sale'], []),
        new RenderContext('happenv', true),
    );

    expect($text)->toContain('happenv/core')->and($text)->toContain('happenv/sale');
});

it('why text shortens default-vendor path entries', function (): void {
    $text = (new WhyTextRenderer)->render(
        new WhyReport('happenv/amazon', 'happenv/core', ['happenv/amazon', 'acme/catalog', 'happenv/core']),
        new RenderContext('happenv'),
    );

    expect($text)->toContain('amazon')->not->toContain('happenv/amazon')
        ->and($text)->toContain('acme/catalog');
});

it('modules text shortens default-vendor names', function (): void {
    $report = new ModulesReport([
        ['name' => 'happenv/core', 'dependencies' => [], 'path' => 'x'],
        ['name' => 'acme/catalog', 'dependencies' => [], 'path' => 'y'],
    ], false);

    $text = (new ModulesTextRenderer)->render($report, new RenderContext('happenv'));

    expect($text)->toContain('1. core')->and($text)->toContain('2. acme/catalog');
});
