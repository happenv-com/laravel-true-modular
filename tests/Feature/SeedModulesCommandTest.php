<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Commands\SeedModulesCommand;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // The package registers commands via its custom Application::initialize(),
    // which testbench's standard Application does not call — register it here.
    Artisan::registerCommand(app(SeedModulesCommand::class));
});

describe('SeedModulesCommand', function (): void {
    describe('modules:seed --show-order', function (): void {
        it('displays module execution order', function (): void {
            $this->artisan('modules:seed', ['--show-order' => true])
                ->assertSuccessful()
                ->expectsOutputToContain('Module execution order')
                ->expectsOutputToContain('myapp/core');
        });

        it('shows dependencies and seeder count', function (): void {
            $this->artisan('modules:seed', ['--show-order' => true])
                ->assertSuccessful()
                ->expectsOutputToContain('Depends On')
                ->expectsOutputToContain('seeders');
        });
    });

    describe('modules:seed --module', function (): void {
        it('seeds only specified module', function (): void {
            // Core has no seeders, so it should warn but still succeed. The point
            // is that the command accepts the --module option.
            $this->artisan('modules:seed', ['--module' => 'myapp/core'])
                ->assertSuccessful();
        });
    });
});
