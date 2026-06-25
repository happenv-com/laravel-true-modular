<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;

it('resolves the architecture services from the container', function (): void {
    expect(app(ModuleLocator::class))->toBeInstanceOf(ModuleLocator::class)
        ->and(app(RendererRegistry::class))->toBeInstanceOf(RendererRegistry::class);
});

it('builds a populated index through the container', function (): void {
    $index = app(ArchitectureIndexBuilder::class)->build();

    expect($index)->toBeInstanceOf(ArchitectureIndex::class)
        ->and($index->modules()->names())->toContain('myapp/sale', 'myapp/amazon');
});
