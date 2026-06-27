<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

use function Safe\glob;

beforeEach(function (): void {
    app()->singleton(
        ModuleLocator::class,
        static fn ($app): AppModulesLocator => new AppModulesLocator($app->make(ModuleRegistry::class), 'myapp'),
    );
});

it('creates a migration in the resolved module path for a bare name', function (): void {
    $dir = appModulesFixture().'/sale/database/migrations';

    $this->artisan('module:make:migration', ['module' => 'sale', 'name' => 'create_widgets_table'])
        ->assertSuccessful();

    $created = glob($dir.'/*_create_widgets_table.php');

    expect($created)->not->toBeEmpty();

    foreach ($created as $file) {
        unlink($file);
    }
});

it('fails with a friendly error for an unknown module', function (): void {
    $this->artisan('module:make:migration', ['module' => 'nope', 'name' => 'create_x_table'])
        ->assertFailed()
        ->expectsOutputToContain('Unknown module: nope');
});
