<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ListModulesCommand;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ListModulesCommand(
        app(ModuleRegistry::class),
        app(ArchitectureIndexBuilder::class),
        app(RendererRegistry::class),
    ));
});

it('lists modules in dependency order', function (): void {
    $this->artisan('module:list --simple')
        ->assertSuccessful()
        ->expectsOutputToContain('myapp/kernel')
        ->expectsOutputToContain('myapp/sale');
});

it('emits json with a modules schema block and dependency data', function (): void {
    $exit = Artisan::call('module:list', ['--format' => 'json']);

    expect($exit)->toBe(0);

    $json = json_decode(Artisan::output(), associative: true);

    expect($json['schema']['name'])->toBe('modules')
        ->and($json['order'])->toBe('topological')
        ->and($json['modules'][0]['name'])->toBe('myapp/kernel')
        ->and(collect($json['modules'])->firstWhere('name', 'myapp/sale')['dependencies'])
        ->toContain('myapp/core', 'myapp/pim');
});

it('reverses the order with --reverse', function (): void {
    $exit = Artisan::call('module:list', ['--format' => 'json', '--reverse' => true]);

    expect($exit)->toBe(0);

    $json = json_decode(Artisan::output(), associative: true);

    expect($json['order'])->toBe('reverse')
        ->and(end($json['modules'])['name'])->toBe('myapp/kernel');
});
