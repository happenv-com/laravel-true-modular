<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\WhyAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ModuleWhyCommand;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ModuleWhyCommand(
        app(ArchitectureIndexBuilder::class),
        app(WhyAnalyzer::class),
        app(RendererRegistry::class),
    ));
});

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
