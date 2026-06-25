<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\WhyAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function whyIndex(): ArchitectureIndex
{
    $modules = [];
    foreach (['core', 'pim', 'sale', 'amazon'] as $name) {
        $modules[$name] = new ModuleDescriptor($name, $name, '/'.$name, null, null, null, [], false);
    }

    $graph = new DependencyGraph([
        'core' => [],
        'pim' => ['core'],
        'sale' => ['pim'],
        'amazon' => ['sale'],
    ]);

    return new ArchitectureIndex($modules, $graph);
}

it('returns the dependency path from a module to its dependency', function (): void {
    $report = (new WhyAnalyzer)->analyze(whyIndex(), 'amazon', 'core');

    expect($report->from)->toBe('amazon')
        ->and($report->to)->toBe('core')
        ->and($report->path)->toBe(['amazon', 'sale', 'pim', 'core']);
});

it('returns null path when no dependency exists', function (): void {
    $report = (new WhyAnalyzer)->analyze(whyIndex(), 'core', 'amazon');

    expect($report->path)->toBeNull()
        ->and($report->toArray())->toBe(['from' => 'core', 'to' => 'amazon', 'path' => null]);
});

it('reports the why schema', function (): void {
    expect((new WhyAnalyzer)->analyze(whyIndex(), 'amazon', 'core')->schemaName())->toBe('why');
});

it('throws for unknown endpoints', function (): void {
    (new WhyAnalyzer)->analyze(whyIndex(), 'amazon', 'nope');
})->throws(InvalidArgumentException::class);
