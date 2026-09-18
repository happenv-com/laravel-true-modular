<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Happenv\LaravelTrueModular\ModuleSystem\PublishedMigrations;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use RuntimeException;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\PcreException;

use function Safe\glob;
use function Safe\preg_replace;

/**
 * Hands a module's migrations to the two consumers that need them — the migrator and
 * `vendor:publish` — and does the work for each ONLY when that consumer actually runs.
 *
 * Both used to be computed eagerly at every boot, and "boot" is far more often than it
 * sounds: every HTTP request, every artisan command, every queue worker and every single
 * test (a test suite boots a fresh application per test). Measured on a host with ~110
 * modules and ~800 migrations: listing the directories and naming ~800 publish targets was
 * ~12% of the whole per-test boot, spent on results that nothing read — the migrator is
 * resolved only by the commands that migrate, and the publish targets are read only by
 * `vendor:publish`. `runningInConsole()` is not the right guard for either: tests, queue
 * workers and the scheduler all run in the console too.
 *
 * @mixin ModuleProvider
 */
trait ProcessMigrations
{
    private ?PublishedMigrations $publishedMigrations = null;

    /**
     * @throws BindingResolutionException
     * @throws RuntimeException
     */
    protected function processMigrations(): static
    {
        if ($this->module->discoversMigrations) {
            $this->discoverModuleMigrations();

            return $this;
        }

        $migrationFileNames = $this->module->migrationFileNames;

        if ($migrationFileNames === []) {
            return $this;
        }

        if ($this->module->runsMigrations) {
            $this->loadMigrationsWhenMigrating(fn (): array => array_map(
                $this->declaredMigrationPath(...),
                $migrationFileNames,
            ));
        }

        $this->publishMigrationsWhenPublishing(function () use ($migrationFileNames): array {
            $now = Date::now();
            $publishable = [];

            foreach ($migrationFileNames as $migrationFileName) {
                $publishable[$this->declaredMigrationPath($migrationFileName)]
                    = $this->generateMigrationName($migrationFileName, $now->addSecond());
            }

            return $publishable;
        });

        return $this;
    }

    /**
     * @throws BindingResolutionException
     * @throws RuntimeException
     */
    protected function discoverModuleMigrations(): void
    {
        $migrationsPath = trim((string) $this->module->migrationsPath, '/');
        $migrationsDir = $this->module->vendorPath($migrationsPath);

        // Filesystem::files() throws DirectoryNotFoundException on a missing dir,
        // which would crash boot for a module that enables discoversMigrations()
        // but ships no migrations directory (e.g. on a fresh checkout).
        if ($this->moduleDirectoryMissing($migrationsDir, 'migrations discovery')) {
            return;
        }

        if ($this->module->runsMigrations) {
            // Do not load — or, below, publish with a timestamp — non migration files.
            $this->loadMigrationsWhenMigrating(static fn (): array => array_values(array_filter(
                self::listMigrationDirectory($migrationsDir),
                self::isMigrationFile(...),
            )));
        }

        $this->publishMigrationsWhenPublishing(function () use ($migrationsDir): array {
            $now = Date::now();
            $publishable = [];

            foreach (self::listMigrationDirectory($migrationsDir) as $filePath) {
                $publishable[$filePath] = self::isMigrationFile($filePath)
                    ? $this->generateMigrationName(
                        Str::replace(['.stub', '.php'], '', basename($filePath)),
                        $now->addSecond(),
                    )
                    : database_path('migrations/'.basename($filePath));
            }

            return $publishable;
        });
    }

    /**
     * Give the migrator this module's migrations, listing them only once something resolves it.
     *
     * This is `loadMigrationsFrom()` with the listing moved inside: that method already waits
     * for the migrator, but it has to be handed the paths up front, so the directory was read at
     * every boot whether or not anything was about to migrate.
     *
     * @param  Closure(): list<string>  $migrations
     */
    protected function loadMigrationsWhenMigrating(Closure $migrations): void
    {
        $this->callAfterResolving('migrator', static function (Migrator $migrator) use ($migrations): void {
            foreach ($migrations() as $migration) {
                $migrator->path($migration);
            }
        });
    }

    /**
     * Register this module's migration publish targets, working them out only once
     * `vendor:publish` is about to run.
     *
     * Keyed on the command being RESOLVED, not on the `CommandStarting` event: the event is
     * dispatched only for a command run from the shell or through `Artisan::call()`, while
     * `$this->call('vendor:publish')` from inside another command (every package's install
     * command does exactly that) resolves the command and runs it without dispatching
     * anything. Resolution also sees through an abbreviated name (`ven:pub`). Either way
     * it happens before `handle()`, which is where the publish registry is read.
     *
     * Registered in one `publishes()` call per module rather than one per file:
     * `publishes()` re-merges the provider's whole publish array on every call.
     *
     * @param  Closure(): array<string, string>  $publishable
     */
    protected function publishMigrationsWhenPublishing(Closure $publishable): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $group = $this->module->shortName().'-migrations';

        $this->callAfterResolving(VendorPublishCommand::class, function () use ($publishable, $group): void {
            $paths = $publishable();

            if ($paths !== []) {
                $this->publishes($paths, $group);
            }
        });
    }

    /**
     * Where a migration declared by name (not discovered) lives in the module.
     */
    private function declaredMigrationPath(string $migrationFileName): string
    {
        $vendorMigration = $this->module->vendorPath('database/migrations/'.$migrationFileName.'.php');

        // Support for the .stub file extension
        return file_exists($vendorMigration) ? $vendorMigration : $vendorMigration.'.stub';
    }

    private static function isMigrationFile(string $filePath): bool
    {
        return Str::endsWith($filePath, ['.php', '.php.stub']);
    }

    /**
     * List the files directly inside a module's migrations directory, in name order.
     *
     * Deliberately not `Filesystem::files()`. That builds a Symfony Finder per module,
     * and on a host with ~70 modules shipping migrations the Finder alone cost more than
     * everything else migration discovery does. The four Finder behaviours this discovery
     * relies on (files only, no recursion, dot files skipped, name order) are exactly what
     * a plain `glob()` already gives.
     *
     * @return list<string>
     *
     * @throws FilesystemException
     */
    private static function listMigrationDirectory(string $directory): array
    {
        $files = array_values(array_filter(glob($directory.'/*'), is_file(...)));

        // glob() defers to the platform collation; Finder compared with strcmp.
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @throws BindingResolutionException
     * @throws FilesystemException
     * @throws PcreException
     */
    protected function generateMigrationName(string $migrationFileName, Carbon|CarbonImmutable $now): string
    {
        $migrationsPath = 'migrations/'.dirname($migrationFileName).'/';
        $migrationFileName = basename($migrationFileName);

        $len = strlen($migrationFileName) + 4;

        if (Str::contains($migrationFileName, '/')) {
            $migrationsPath .= Str::of($migrationFileName)->beforeLast('/')->finish('/');
            $migrationFileName = Str::of($migrationFileName)->afterLast('/');
        }

        foreach ($this->publishedMigrations()->matching(database_path($migrationsPath.'*.php')) as $filename) {
            if ((substr((string) $filename, -$len) === $migrationFileName.'.php')) {
                return $filename;
            }
        }

        $migrationFileName = self::stripTimestampPrefix($migrationFileName);
        $timestamp = $now->format('Y_m_d_His');
        $formattedFileName = Str::of($migrationFileName)->snake()->finish('.php');

        return database_path(sprintf('%s%s_%s', $migrationsPath, $timestamp, $formattedFileName));
    }

    /**
     * The application-wide listing of already-published migrations, resolved once per
     * provider so that 650 migration files do not mean 650 container lookups either.
     *
     * @throws BindingResolutionException
     */
    protected function publishedMigrations(): PublishedMigrations
    {
        if ($this->publishedMigrations instanceof PublishedMigrations) {
            return $this->publishedMigrations;
        }

        $this->app->singletonIf(PublishedMigrations::class);

        return $this->publishedMigrations = $this->app->make(PublishedMigrations::class);
    }

    /**
     * @throws PcreException
     */
    private static function stripTimestampPrefix(string $filename): string
    {
        return preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
    }
}
