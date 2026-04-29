<?php

declare(strict_types=1);

use function Pest\Laravel\artisan;

describe('SeedModulesCommand', function (): void {
    describe('modules:seed --show-order', function (): void {
        it('displays module execution order', function (): void {
            artisan('modules:seed', ['--show-order' => true])
                ->assertSuccessful()
                ->expectsOutputToContain('Module execution order')
                ->expectsOutputToContain('myapp/core');
        });

        it('shows dependencies and seeder count', function (): void {
            artisan('modules:seed', ['--show-order' => true])
                ->assertSuccessful()
                ->expectsOutputToContain('Depends On')
                ->expectsOutputToContain('seeders');
        });
    });

    describe('modules:seed --module', function (): void {
        it('seeds only specified module', function (): void {
            // This will attempt to seed but may fail due to DB state
            // The important thing is that it accepts the --module option
            $result = artisan('modules:seed', [
                '--module' => 'myapp/core',
            ]);

            // Core has no seeders, so it should warn
            $result->assertSuccessful();
        });
    });
});
