<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\GraphAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function graphIndex(): ArchitectureIndex
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

it('builds a full dependents graph rooted at leaves', function (): void {
    $report = (new GraphAnalyzer())->analyze(graphIndex());

    expect($report->roots)->toBe(['core'])
        ->and($report->dependents['core'])->toBe(['pim'])
        ->and($report->dependents['sale'])->toBe(['amazon']);
});

it('restricts the graph to a given root subtree', function (): void {
    $report = (new GraphAnalyzer())->analyze(graphIndex(), 'pim');

    expect($report->root)->toBe('pim')
        ->and($report->roots)->toBe(['pim'])
        ->and($report->dependents)->toHaveKeys(['pim', 'sale', 'amazon'])
        ->and($report->dependents)->not->toHaveKey('core');
});

it('reports the graph schema', function (): void {
    expect((new GraphAnalyzer())->analyze(graphIndex())->schemaName())->toBe('graph');
});

it('throws for an unknown root', function (): void {
    (new GraphAnalyzer())->analyze(graphIndex(), 'nope');
})->throws(InvalidArgumentException::class);
