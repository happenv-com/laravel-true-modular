<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ListModulesCommand;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleActivation;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ListModulesCommand(
        app(ModuleRegistry::class),
        app(ArchitectureIndexBuilder::class),
        app(RendererRegistry::class),
    ));
});

afterEach(fn () => Application::modulesNamespace(Application::DEFAULT_MODULES_NAMESPACE));

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

it('lists modules by short name for the default vendor', function (): void {
    Application::modulesNamespace('Myapp');

    Artisan::registerCommand(new ListModulesCommand(
        app(ModuleRegistry::class),
        app(ArchitectureIndexBuilder::class),
        app(RendererRegistry::class),
    ));

    $this->artisan('module:list', ['--simple' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('core')
        ->doesntExpectOutputToContain('myapp/core');
});

it('lists full names with --with-vendor', function (): void {
    Application::modulesNamespace('Myapp');

    Artisan::registerCommand(new ListModulesCommand(
        app(ModuleRegistry::class),
        app(ArchitectureIndexBuilder::class),
        app(RendererRegistry::class),
    ));

    $this->artisan('module:list', ['--simple' => true, '--with-vendor' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('myapp/core');
});

it('keeps full names in json regardless of vendor', function (): void {
    Application::modulesNamespace('Myapp');

    Artisan::registerCommand(new ListModulesCommand(
        app(ModuleRegistry::class),
        app(ArchitectureIndexBuilder::class),
        app(RendererRegistry::class),
    ));

    $this->artisan('module:list', ['--format' => 'json'])
        ->assertSuccessful()
        ->expectsOutputToContain('myapp/core');
});

describe('activation state', function (): void {
    afterEach(function (): void {
        unset($_ENV[ModuleActivation::ENV_KEY], $_SERVER[ModuleActivation::ENV_KEY]);
    });

    it('reports every module as enabled when no channel disables anything', function (): void {
        $_ENV[ModuleActivation::ENV_KEY] = '';

        $exit = Artisan::call('module:list', ['--format' => 'json']);
        $json = json_decode(Artisan::output(), associative: true);

        expect($exit)->toBe(0)
            ->and(collect($json['modules'])->pluck('enabled')->unique()->all())->toBe([true]);
    });

    it('marks a disabled module in the json report', function (): void {
        $_ENV[ModuleActivation::ENV_KEY] = 'myapp/amazon';

        Artisan::call('module:list', ['--format' => 'json']);
        $json = json_decode(Artisan::output(), associative: true);

        expect(collect($json['modules'])->firstWhere('name', 'myapp/amazon')['enabled'])->toBeFalse()
            ->and(collect($json['modules'])->firstWhere('name', 'myapp/pim')['enabled'])->toBeTrue();
    });

    it('shows the state in the table view', function (): void {
        $_ENV[ModuleActivation::ENV_KEY] = 'myapp/amazon';

        $this->artisan('module:list')
            ->assertSuccessful()
            ->expectsOutputToContain('disabled');
    });

    it('narrows the list to switched-off modules on demand', function (): void {
        $_ENV[ModuleActivation::ENV_KEY] = 'myapp/amazon';

        Artisan::call('module:list', ['--format' => 'json', '--only-disabled' => true]);
        $json = json_decode(Artisan::output(), associative: true);

        expect($json['modules'])->toHaveCount(1)
            ->and($json['modules'][0]['name'])->toBe('myapp/amazon');
    });
});
