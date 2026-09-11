<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Tests\Doubles\RecordingMigrationModuleProvider;

/**
 * Lay out a module whose migrations directory holds one of everything discovery has to
 * tell apart, and return the provider after it has walked it.
 */
function discoverFixtureMigrations(bool $runsMigrations = true): RecordingMigrationModuleProvider
{
    $base = sys_get_temp_dir().'/true-modular-migrations-'.bin2hex(random_bytes(6));
    $migrations = $base.'/database/migrations';

    mkdir($migrations.'/nested', recursive: true);
    mkdir($base.'/src');

    file_put_contents($migrations.'/2026_01_02_000000_create_orders_table.php', '<?php');
    file_put_contents($migrations.'/2026_01_01_000000_create_customers_table.php', '<?php');
    file_put_contents($migrations.'/2026_01_03_000000_create_invoices_table.php.stub', '<?php');
    file_put_contents($migrations.'/seed-data.json', '{}');
    file_put_contents($migrations.'/.hidden_migration.php', '<?php');
    file_put_contents($migrations.'/nested/2026_01_04_000000_nested.php', '<?php');

    RecordingMigrationModuleProvider::$moduleBaseDir = $base.'/src';

    $provider = new RecordingMigrationModuleProvider(app());
    $provider->runsMigrations = $runsMigrations;
    $provider->register();
    $provider->discoverMigrations();

    return $provider;
}

it('registers every discovered migration in a single publish call per module', function (): void {
    $provider = discoverFixtureMigrations();

    expect($provider->publishCalls)->toHaveCount(1)
        ->and($provider->publishCalls[0]['groups'])->toBe('acme/catalog-migrations')
        ->and($provider->publishCalls[0]['paths'])->toHaveCount(4);
});

it('hands the migrator every discovered migration in a single call per module', function (): void {
    $provider = discoverFixtureMigrations();

    expect($provider->loadCalls)->toHaveCount(1)
        ->and($provider->loadCalls[0])->toBeArray()
        ->and($provider->loadCalls[0])->toHaveCount(3);
});

it('discovers files in name order, skipping dot files and subdirectories', function (): void {
    $provider = discoverFixtureMigrations();

    $names = array_map(basename(...), array_keys($provider->publishCalls[0]['paths']));

    expect($names)->toBe([
        '2026_01_01_000000_create_customers_table.php',
        '2026_01_02_000000_create_orders_table.php',
        '2026_01_03_000000_create_invoices_table.php.stub',
        'seed-data.json',
    ]);
});

it('loads only the migrations, never the files that merely ship alongside them', function (): void {
    $provider = discoverFixtureMigrations();

    $loaded = array_map(basename(...), (array) $provider->loadCalls[0]);

    expect($loaded)->toBe([
        '2026_01_01_000000_create_customers_table.php',
        '2026_01_02_000000_create_orders_table.php',
        '2026_01_03_000000_create_invoices_table.php.stub',
    ]);
});

it('publishes a non-migration file under its own name and a migration under a timestamp', function (): void {
    $provider = discoverFixtureMigrations();

    $targets = [];
    foreach ($provider->publishCalls[0]['paths'] as $from => $to) {
        $targets[basename($from)] = basename($to);
    }

    expect($targets['seed-data.json'])->toBe('seed-data.json')
        ->and($targets['2026_01_01_000000_create_customers_table.php'])
        ->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_customers_table\.php$/');
});

it('publishes without loading when the module does not run its own migrations', function (): void {
    $provider = discoverFixtureMigrations(runsMigrations: false);

    expect($provider->publishCalls)->toHaveCount(1)
        ->and($provider->loadCalls)->toBe([]);
});

it('reads the published migrations directory once for the whole application', function (): void {
    // One listing per application instance, not one per module and not one per file —
    // and scoped to the application, so a second boot in the same process reads again.
    $first = discoverFixtureMigrations();
    $second = discoverFixtureMigrations();

    expect($second->publishedMigrationsIndex())->toBe($first->publishedMigrationsIndex());
});
