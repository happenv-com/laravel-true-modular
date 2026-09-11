<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Happenv\LaravelTrueModular\ModuleSystem\PublishedMigrations;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use RuntimeException;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\PcreException;

use function Safe\glob;
use function Safe\preg_replace;

/**
 * @mixin ModuleProvider
 */
trait ProcessMigrations
{
    private ?PublishedMigrations $publishedMigrations = null;

    /**
     * @throws BindingResolutionException
     * @throws FilesystemException
     * @throws PcreException
     * @throws RuntimeException
     */
    protected function processMigrations(): static
    {
        if ($this->module->discoversMigrations) {
            $this->discoverModuleMigrations();

            return $this;
        }

        $now = Date::now();

        foreach ($this->module->migrationFileNames as $migrationFileName) {
            $vendorMigration = $this->module->vendorPath('database/migrations/'.$migrationFileName.'.php');

            // Support for the .stub file extension
            if (! file_exists($vendorMigration)) {
                $vendorMigration .= '.stub';
            }

            if ($this->app->runningInConsole()) {
                $appMigration = $this->generateMigrationName($migrationFileName, $now->addSecond());

                $this->publishes(
                    [$vendorMigration => $appMigration],
                    $this->module->shortName().'-migrations'
                );
            }

            if ($this->module->runsMigrations) {
                $this->loadMigrationsFrom($vendorMigration);
            }
        }

        return $this;
    }

    /**
     * @throws BindingResolutionException
     * @throws FilesystemException
     * @throws PcreException
     * @throws RuntimeException
     */
    protected function discoverModuleMigrations(): void
    {
        $now = Date::now();
        $migrationsPath = trim((string) $this->module->migrationsPath, '/');
        $migrationsDir = $this->module->vendorPath($migrationsPath);

        // Filesystem::files() throws DirectoryNotFoundException on a missing dir,
        // which would crash boot for a module that enables discoversMigrations()
        // but ships no migrations directory (e.g. on a fresh checkout).
        if ($this->moduleDirectoryMissing($migrationsDir, 'migrations discovery')) {
            return;
        }

        $runningInConsole = $this->app->runningInConsole();
        $publishable = [];
        $loadable = [];

        foreach (self::listMigrationDirectory($migrationsDir) as $filePath) {
            // Do not treat — publish with a timestamp, or load — non migration files.
            $isMigration = Str::endsWith($filePath, ['.php', '.php.stub']);

            if ($runningInConsole) {
                $publishable[$filePath] = $isMigration
                    ? $this->generateMigrationName(
                        Str::replace(['.stub', '.php'], '', basename($filePath)),
                        $now->addSecond(),
                    )
                    : database_path('migrations/'.basename($filePath));
            }

            if ($isMigration) {
                $loadable[] = $filePath;
            }
        }

        // Registered in one call per module rather than one per file: `publishes()`
        // re-merges the provider's whole publish array on every call, and
        // `loadMigrationsFrom()` parks another closure on the container for each one.
        if ($publishable !== []) {
            $this->publishes($publishable, $this->module->shortName().'-migrations');
        }

        if ($this->module->runsMigrations && $loadable !== []) {
            $this->loadMigrationsFrom($loadable);
        }
    }

    /**
     * List the files directly inside a module's migrations directory, in name order.
     *
     * Deliberately not `Filesystem::files()`. That builds a Symfony Finder per module,
     * and on a host with ~70 modules shipping migrations the Finder alone cost more than
     * everything else migration discovery does — on a path that runs at every boot. The
     * four Finder behaviours this discovery relies on (files only, no recursion, dot
     * files skipped, name order) are exactly what a plain `glob()` already gives.
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
