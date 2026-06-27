<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;

class WidgetManifestProvider extends ModuleProvider
{
    public function configureModule(Module $module): void
    {
        $module->name('acme/laravel-widget')
            ->hasHealthChecks('Acme\\Widget\\Health\\WidgetCheck');
    }
}

class GadgetManifestProvider extends ModuleProvider
{
    public function configureModule(Module $module): void
    {
        $module->name('acme/laravel-gadget');
    }
}

it('exposes a booted module\'s declared health checks through the manifest', function (): void {
    $this->app->register(WidgetManifestProvider::class);

    $module = app(ModuleManifestRepository::class)->find('widget');

    expect($module)->not->toBeNull()
        ->and($module->healthChecks)->toBe(['Acme\\Widget\\Health\\WidgetCheck']);
});

it('collects every registered module into one shared manifest', function (): void {
    $this->app->register(WidgetManifestProvider::class);
    $this->app->register(GadgetManifestProvider::class);

    $repository = app(ModuleManifestRepository::class);

    expect($repository->all())->toHaveKeys(['widget', 'gadget'])
        ->and($repository->find('gadget')->healthChecks)->toBe([]);
});
