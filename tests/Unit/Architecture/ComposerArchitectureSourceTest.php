<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Source\ComposerArchitectureSource;
use Happenv\LaravelTrueModular\Architecture\Source\DependenciesContribution;
use Happenv\LaravelTrueModular\Architecture\Source\ModulesContribution;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

function composerSource(): ComposerArchitectureSource
{
    $tree = new ModuleRegistry(appModulesFixture());

    return new ComposerArchitectureSource($tree, new AppModulesLocator($tree, 'myapp'));
}

it('yields a modules contribution and a dependencies contribution', function (): void {
    $contributions = iterator_to_array(composerSource()->contribute(), false);

    $modules = array_values(array_filter($contributions, fn ($c): bool => $c instanceof ModulesContribution));
    $deps = array_values(array_filter($contributions, fn ($c): bool => $c instanceof DependenciesContribution));

    expect($modules)->toHaveCount(1)
        ->and($deps)->toHaveCount(1)
        ->and(array_keys($modules[0]->modules))->toContain('myapp/sale')
        ->and($deps[0]->edges['myapp/sale'])->toContain('myapp/core', 'myapp/pim');
});
