<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Tests\Doubles\RecordingMigrationModuleProvider;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Illuminate\Support\Facades\Artisan;

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

function fixtureMigrationsDirectory(): string
{
    return dirname(RecordingMigrationModuleProvider::$moduleBaseDir).'/database/migrations';
}

/**
 * What the migrator was given from the fixture module, by file name — resolving it, which
 * is the moment a module's migrations are listed.
 *
 * @return list<string>
 */
function migratorPathsFromFixture(): array
{
    /** @var Migrator $migrator */
    $migrator = app('migrator');

    // Module paths are built as `<module>/src/../database/migrations`, so compare resolved directories.
    $directory = realpath(fixtureMigrationsDirectory());

    return array_values(array_map(basename(...), array_filter(
        $migrator->paths(),
        static fn (string $path): bool => realpath(dirname($path)) === $directory,
    )));
}

it('lists the migrations when the migrator is resolved, not when the module boots', function (): void {
    discoverFixtureMigrations();

    // Written after discovery ran: only a listing made at resolution can see it.
    file_put_contents(fixtureMigrationsDirectory().'/2026_01_05_000000_create_refunds_table.php', '<?php');

    expect(migratorPathsFromFixture())->toContain('2026_01_05_000000_create_refunds_table.php');
});

it('hands the migrator only the migrations, in name order, never the files that merely ship alongside them', function (): void {
    discoverFixtureMigrations();

    expect(migratorPathsFromFixture())->toBe([
        '2026_01_01_000000_create_customers_table.php',
        '2026_01_02_000000_create_orders_table.php',
        '2026_01_03_000000_create_invoices_table.php.stub',
    ]);
});

it('names no publish targets until vendor:publish is resolved', function (): void {
    $provider = discoverFixtureMigrations();

    expect($provider->publishCalls)->toBe([]);

    app(VendorPublishCommand::class);

    expect($provider->publishCalls)->toHaveCount(1)
        ->and($provider->publishCalls[0]['groups'])->toBe('acme/catalog-migrations')
        ->and($provider->publishCalls[0]['paths'])->toHaveCount(4);
});

it('names the publish targets for a vendor:publish called from inside another command', function (): void {
    // A nested `$this->call()` dispatches no CommandStarting; resolving the command is the
    // only moment both paths share. Every package's install command publishes this way.
    $provider = discoverFixtureMigrations();

    Artisan::command('acme:install', function (): void {
        /** @var Command $this */
        $this->callSilently('vendor:publish', ['--tag' => 'acme/nothing-to-publish']);
    });

    Artisan::call('acme:install');

    expect($provider->publishCalls)->toHaveCount(1);
});

it('names no publish targets for a command that is not vendor:publish', function (): void {
    $provider = discoverFixtureMigrations();

    Artisan::command('acme:noop', function (): void {
        /** @var Command $this */
        $this->line('nothing to do');
    });
    Artisan::call('acme:noop');

    expect($provider->publishCalls)->toBe([]);
});

it('publishes files in name order, skipping dot files and subdirectories', function (): void {
    $provider = discoverFixtureMigrations();
    app(VendorPublishCommand::class);

    $names = array_map(basename(...), array_keys($provider->publishCalls[0]['paths']));

    expect($names)->toBe([
        '2026_01_01_000000_create_customers_table.php',
        '2026_01_02_000000_create_orders_table.php',
        '2026_01_03_000000_create_invoices_table.php.stub',
        'seed-data.json',
    ]);
});

it('publishes a non-migration file under its own name and a migration under a timestamp', function (): void {
    $provider = discoverFixtureMigrations();
    app(VendorPublishCommand::class);

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
    app(VendorPublishCommand::class);

    expect($provider->publishCalls)->toHaveCount(1)
        ->and(migratorPathsFromFixture())->toBe([]);
});

it('reads the published migrations directory once for the whole application', function (): void {
    // One listing per application instance, not one per module and not one per file —
    // and scoped to the application, so a second boot in the same process reads again.
    $first = discoverFixtureMigrations();
    $second = discoverFixtureMigrations();

    expect($second->publishedMigrationsIndex())->toBe($first->publishedMigrationsIndex());
});
