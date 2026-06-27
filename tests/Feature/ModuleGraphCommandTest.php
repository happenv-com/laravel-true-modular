<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\GraphAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ModuleGraphCommand;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ModuleGraphCommand(
        app(ArchitectureIndexBuilder::class),
        app(GraphAnalyzer::class),
        app(RendererRegistry::class),
        app(ModuleLocator::class),
    ));
});

function registerGraphWithVendor(string $vendor): void
{
    Artisan::registerCommand(new ModuleGraphCommand(
        app(ArchitectureIndexBuilder::class),
        app(GraphAnalyzer::class),
        app(RendererRegistry::class),
        new AppModulesLocator(app(ModuleRegistry::class), $vendor),
    ));
}

it('prints the dependency tree by default', function (): void {
    $this->artisan('module:graph')
        ->assertSuccessful()
        ->expectsOutputToContain('myapp/kernel');
});

it('prints mermaid output', function (): void {
    $this->artisan('module:graph', ['--format' => 'mermaid'])
        ->assertSuccessful()
        ->expectsOutputToContain('graph TD');
});

it('restricts the graph to a root', function (): void {
    $this->artisan('module:graph', ['--root' => 'myapp/sale', '--format' => 'json'])
        ->assertSuccessful()
        ->expectsOutputToContain('"root": "myapp/sale"');
});

it('accepts a bare module name for --root', function (): void {
    registerGraphWithVendor('myapp');

    $this->artisan('module:graph', ['--root' => 'sale', '--format' => 'json'])
        ->assertSuccessful()
        ->expectsOutputToContain('"root": "myapp/sale"');
});

it('fails with a friendly error for an unknown bare root name', function (): void {
    registerGraphWithVendor('myapp');

    $this->artisan('module:graph', ['--root' => 'nope'])
        ->assertFailed()
        ->expectsOutputToContain('Unknown module: nope')
        ->expectsOutputToContain('Resolved to: myapp/nope');
});
