<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;
use Happenv\LaravelTrueModular\Architecture\Source\ArchitectureSource;
use Happenv\LaravelTrueModular\Architecture\Source\DependenciesContribution;
use Happenv\LaravelTrueModular\Architecture\Source\ModulesContribution;

function descriptorFor(string $name): ModuleDescriptor
{
    return new ModuleDescriptor($name, $name, '/'.$name, null, null, null, [], false);
}

function sourceA(): ArchitectureSource
{
    return new class implements ArchitectureSource
    {
        public function contribute(): iterable
        {
            yield new ModulesContribution(['b/two' => descriptorFor('b/two'), 'a/one' => descriptorFor('a/one')]);
            yield new DependenciesContribution(['b/two' => ['a/one']]);
        }
    };
}

function sourceB(): ArchitectureSource
{
    return new class implements ArchitectureSource
    {
        public function contribute(): iterable
        {
            yield new ModulesContribution(['c/three' => descriptorFor('c/three')]);
            yield new DependenciesContribution(['c/three' => ['b/two']]);
        }
    };
}

it('builds an index merging all sources', function (): void {
    $index = (new ArchitectureIndexBuilder([sourceA(), sourceB()]))->build();

    expect($index)->toBeInstanceOf(ArchitectureIndex::class)
        ->and($index->modules()->names())->toBe(['a/one', 'b/two', 'c/three'])
        ->and($index->graph()->dependencies('c/three'))->toBe(['b/two']);
});

it('is order-independent (permutation test)', function (): void {
    $forward = (new ArchitectureIndexBuilder([sourceA(), sourceB()]))->build()->toArray();
    $reversed = (new ArchitectureIndexBuilder([sourceB(), sourceA()]))->build()->toArray();

    expect($reversed)->toBe($forward);
});
