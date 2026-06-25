<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\GraphAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ModuleGraphCommand;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ModuleGraphCommand(
        app(ArchitectureIndexBuilder::class),
        app(GraphAnalyzer::class),
        app(RendererRegistry::class),
    ));
});

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
