<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\ImpactAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function impactIndex(): ArchitectureIndex
{
    $names = ['core', 'pim', 'sale', 'amazon'];
    $modules = [];
    foreach ($names as $name) {
        $modules[$name] = new ModuleDescriptor($name, $name, '/'.$name, null, null, null, [], $name === 'core');
    }

    $graph = new DependencyGraph([
        'core' => [],
        'pim' => ['core'],
        'sale' => ['pim', 'core'],
        'amazon' => ['sale'],
    ]);

    return new ArchitectureIndex($modules, $graph);
}

it('separates direct and indirect impact', function (): void {
    $report = (new ImpactAnalyzer)->analyze(impactIndex(), 'core');

    expect($report->module)->toBe('core')
        ->and($report->direct)->toBe(['pim', 'sale'])
        ->and($report->indirect)->toBe(['amazon'])
        ->and($report->total())->toBe(3);
});

it('serializes with the impact schema name', function (): void {
    $report = (new ImpactAnalyzer)->analyze(impactIndex(), 'core');

    expect($report->schemaName())->toBe('impact')
        ->and($report->schemaVersion())->toBe(1)
        ->and($report->toArray())->toBe([
            'module' => 'core',
            'direct' => ['pim', 'sale'],
            'indirect' => ['amazon'],
            'total' => 3,
        ]);
});

it('throws for an unknown module', function (): void {
    (new ImpactAnalyzer)->analyze(impactIndex(), 'nope');
})->throws(InvalidArgumentException::class);
