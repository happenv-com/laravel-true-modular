<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;

function graphFixture(): DependencyGraph
{
    // deps: amazon -> sale -> {core, pim -> core}; core -> kernel
    return new DependencyGraph([
        'amazon' => ['sale'],
        'sale' => ['pim', 'core'],
        'pim' => ['core'],
        'core' => ['kernel'],
        'kernel' => [],
    ]);
}

it('lists nodes sorted', function (): void {
    expect(graphFixture()->nodes())->toBe(['amazon', 'core', 'kernel', 'pim', 'sale']);
});

it('returns direct dependencies sorted', function (): void {
    expect(graphFixture()->dependencies('sale'))->toBe(['core', 'pim']);
});

it('returns direct dependents sorted', function (): void {
    expect(graphFixture()->dependents('core'))->toBe(['pim', 'sale']);
});

it('computes transitive dependencies', function (): void {
    expect(graphFixture()->transitiveDependencies('amazon'))
        ->toBe(['core', 'kernel', 'pim', 'sale']);
});

it('computes transitive dependents', function (): void {
    expect(graphFixture()->transitiveDependents('core'))
        ->toBe(['amazon', 'pim', 'sale']);
});

it('computes fan-in and fan-out', function (): void {
    expect(graphFixture()->fanIn('core'))->toBe(2)
        ->and(graphFixture()->fanOut('sale'))->toBe(2);
});

it('finds the shortest dependency path', function (): void {
    expect(graphFixture()->path('amazon', 'core'))->toBe(['amazon', 'sale', 'core']);
});

it('returns null when no path exists', function (): void {
    expect(graphFixture()->path('core', 'amazon'))->toBeNull();
});

it('computes dependency depth as longest downstream chain', function (): void {
    expect(graphFixture()->dependencyDepth('amazon'))->toBe(4)
        ->and(graphFixture()->dependencyDepth('kernel'))->toBe(0);
});

it('does not loop on cycles', function (): void {
    $graph = new DependencyGraph(['a' => ['b'], 'b' => ['a']]);

    expect($graph->transitiveDependencies('a'))->toBe(['a', 'b'])
        ->and($graph->dependencyDepth('a'))->toBeGreaterThanOrEqual(0);
});

it('orders nodes with dependencies before dependents', function (): void {
    $order = graphFixture()->topologicalOrder();

    expect($order)->not->toBeNull()
        ->and($order[0])->toBe('kernel')
        ->and(array_search('core', $order, true))->toBeLessThan(array_search('sale', $order, true))
        ->and(array_search('pim', $order, true))->toBeLessThan(array_search('sale', $order, true));
});

it('returns null topological order for a cyclic graph', function (): void {
    $graph = new DependencyGraph(['a' => ['b'], 'b' => ['a']]);

    expect($graph->topologicalOrder())->toBeNull();
});

it('reports distinct cycles, empty when acyclic', function (): void {
    expect(graphFixture()->cycles())->toBe([]);

    $cycles = new DependencyGraph(['a' => ['b'], 'b' => ['a']])->cycles();

    expect($cycles)->toHaveCount(1)
        ->and($cycles[0])->toContain('a', 'b');
});
