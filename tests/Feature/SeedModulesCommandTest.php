<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\Commands\SeedModulesCommand;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // The package registers commands via its custom Application::initialize(),
    // which testbench's standard Application does not call — register it here.
    Artisan::registerCommand(app(SeedModulesCommand::class));
});

function bindLocatorVendor(string $vendor): void
{
    app()->singleton(
        ModuleLocator::class,
        static fn ($app): AppModulesLocator => new AppModulesLocator($app->make(ModuleRegistry::class), $vendor),
    );
}

describe('SeedModulesCommand', function (): void {
    describe('module:seed --show-order', function (): void {
        it('displays module execution order', function (): void {
            $this->artisan('module:seed', ['--show-order' => true])
                ->assertSuccessful()
                ->expectsOutputToContain('Module execution order')
                ->expectsOutputToContain('myapp/core');
        });

        it('shows dependencies and seeder count', function (): void {
            $this->artisan('module:seed', ['--show-order' => true])
                ->assertSuccessful()
                ->expectsOutputToContain('Depends On')
                ->expectsOutputToContain('seeders');
        });
    });

    describe('module:seed --module', function (): void {
        it('seeds only specified module', function (): void {
            // Core has no seeders, so it should warn but still succeed. The point
            // is that the command accepts the --module option.
            $this->artisan('module:seed', ['--module' => 'myapp/core'])
                ->assertSuccessful();
        });

        it('accepts a bare module name for --module', function (): void {
            bindLocatorVendor('myapp');
            Artisan::registerCommand(app(SeedModulesCommand::class));

            $this->artisan('module:seed', ['--module' => 'core'])
                ->assertSuccessful();
        });

        it('fails with a friendly error for an unknown --module', function (): void {
            bindLocatorVendor('myapp');
            Artisan::registerCommand(app(SeedModulesCommand::class));

            $this->artisan('module:seed', ['--module' => 'nope'])
                ->assertFailed()
                ->expectsOutputToContain('Unknown module: nope');
        });
    });
});
