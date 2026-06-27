<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\ImpactAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ModuleImpactCommand;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ModuleImpactCommand(
        app(ArchitectureIndexBuilder::class),
        app(ImpactAnalyzer::class),
        app(RendererRegistry::class),
        app(ModuleLocator::class),
    ));
});

function registerImpactWithVendor(string $vendor): void
{
    Artisan::registerCommand(new ModuleImpactCommand(
        app(ArchitectureIndexBuilder::class),
        app(ImpactAnalyzer::class),
        app(RendererRegistry::class),
        new AppModulesLocator(app(ModuleRegistry::class), $vendor),
    ));
}

it('prints impact as text', function (): void {
    $this->artisan('module:impact', ['module' => 'myapp/core'])
        ->assertSuccessful()
        ->expectsOutputToContain('Total affected:');
});

it('prints impact as json with a schema block', function (): void {
    $this->artisan('module:impact', ['module' => 'myapp/core', '--format' => 'json'])
        ->assertSuccessful()
        ->expectsOutputToContain('"name": "impact"');
});

it('fails for an unsupported schema version', function (): void {
    $this->artisan('module:impact', ['module' => 'myapp/core', '--schema-version' => '2'])
        ->assertFailed()
        ->expectsOutputToContain('Unsupported schema version');
});

it('accepts a bare module name', function (): void {
    registerImpactWithVendor('myapp');

    $this->artisan('module:impact', ['module' => 'kernel'])
        ->assertSuccessful()
        ->expectsOutputToContain('Total affected:');
});

it('fails with a friendly error for an unknown bare name', function (): void {
    registerImpactWithVendor('myapp');

    $this->artisan('module:impact', ['module' => 'nope'])
        ->assertFailed()
        ->expectsOutputToContain('Unknown module: nope')
        ->expectsOutputToContain('Resolved to: myapp/nope');
});
