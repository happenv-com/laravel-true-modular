<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\TextRenderer;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;
use Happenv\LaravelTrueModular\Architecture\Report\WhyReport;

it('renders an impact report as text with sections', function (): void {
    $text = (new TextRenderer())->render(new ImpactReport('core', ['pim', 'sale'], ['amazon']));

    expect($text)->toContain('core')
        ->and($text)->toContain('Direct:')
        ->and($text)->toContain('pim')
        ->and($text)->toContain('Indirect:')
        ->and($text)->toContain('amazon')
        ->and($text)->toContain('Total affected: 3');
});

it('renders a why path as an arrow chain', function (): void {
    $text = (new TextRenderer())->render(new WhyReport('amazon', 'core', ['amazon', 'sale', 'core']));

    expect($text)->toContain('amazon')
        ->and($text)->toContain('sale')
        ->and($text)->toContain('core');
});

it('renders a message when no path exists', function (): void {
    $text = (new TextRenderer())->render(new WhyReport('core', 'amazon', null));

    expect($text)->toContain('no dependency path');
});

it('renders a graph report as an indented tree', function (): void {
    $report = new GraphReport(
        roots: ['core'],
        dependents: ['core' => ['pim'], 'pim' => ['sale'], 'sale' => []],
        root: null,
    );

    $text = (new TextRenderer())->render($report);

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

    $text = (new TextRenderer())->render($report);

    expect($text)->toBeString()
        ->and($text)->toContain('a')
        ->and($text)->toContain('b');
});
