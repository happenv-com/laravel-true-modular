<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleProvider\Exceptions\InvalidModule;
use Happenv\LaravelTrueModular\ModuleProvider\Module;

it('appends configs and rejects declaring the same one twice', function (): void {
    $module = (new Module)->hasConfig('catalog')->hasConfig(['pricing', 'tax']);

    expect($module->configs)->toBe(['catalog', 'pricing', 'tax']);

    $module->hasConfig('catalog');
})->throws(InvalidModule::class, 'already registered');

it('rejects hasMorphMap before the module is named', function (): void {
    (new Module)->hasMorphMap('post', stdClass::class);
})->throws(InvalidModule::class, 'before `hasMorphMap()`');

it('scopes morph map keys by the module name once named', function (): void {
    $module = (new Module)->name('acme/catalog')->hasMorphMap('post', stdClass::class);

    expect($module->morphMapDefinitions)->toBe(['acme/catalog::post' => stdClass::class]);
});

it('keeps the inertia namespace independent of the view namespace', function (): void {
    $module = (new Module)
        ->hasViews('catalog-views')
        ->hasInertiaComponents('catalog-pages');

    expect($module->viewNamespace)->toBe('catalog-views')
        ->and($module->inertiaNamespace)->toBe('catalog-pages');
});
