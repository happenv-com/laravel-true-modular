<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\WhyAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ModuleWhyCommand;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ModuleWhyCommand(
        app(ArchitectureIndexBuilder::class),
        app(WhyAnalyzer::class),
        app(RendererRegistry::class),
        app(ModuleLocator::class),
    ));
});

function registerWhyWithVendor(string $vendor): void
{
    Artisan::registerCommand(new ModuleWhyCommand(
        app(ArchitectureIndexBuilder::class),
        app(WhyAnalyzer::class),
        app(RendererRegistry::class),
        new AppModulesLocator(app(ModuleRegistry::class), $vendor),
    ));
}

it('prints the dependency path between two modules', function (): void {
    $this->artisan('module:why', ['from' => 'myapp/amazon', 'to' => 'myapp/core'])
        ->assertSuccessful()
        ->expectsOutputToContain('myapp/sale');
});

it('fails for an unknown module', function (): void {
    $this->artisan('module:why', ['from' => 'myapp/amazon', 'to' => 'myapp/nope'])
        ->assertFailed()
        ->expectsOutputToContain('Unknown module');
});

it('accepts bare module names', function (): void {
    registerWhyWithVendor('myapp');

    $this->artisan('module:why', ['from' => 'amazon', 'to' => 'core'])
        ->assertSuccessful()
        ->expectsOutputToContain('myapp/sale');
});

it('fails with a friendly error for an unknown bare name in why', function (): void {
    registerWhyWithVendor('myapp');

    $this->artisan('module:why', ['from' => 'amazon', 'to' => 'nope'])
        ->assertFailed()
        ->expectsOutputToContain('Unknown module: nope')
        ->expectsOutputToContain('Resolved to: myapp/nope');
});
