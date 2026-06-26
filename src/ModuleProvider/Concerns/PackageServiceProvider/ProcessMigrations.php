<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Filesystem\Filesystem;
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
    /**
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

        $files = (new Filesystem)->files($migrationsDir);

        foreach ($files as $file) {
            $filePath = $file->getPathname();
            $migrationFileName = Str::replace(['.stub', '.php'], '', $file->getFilename());

            // Publish but do not add timestamp to non migration files
            if (Str::endsWith($filePath, ['.php', '.php.stub'])) {
                $appMigration = $this->generateMigrationName($migrationFileName, $now->addSecond());
            } else {
                $appMigration = database_path('migrations/'.$file->getFilename());
            }

            if ($this->app->runningInConsole()) {
                $this->publishes(
                    [$filePath => $appMigration],
                    $this->module->shortName().'-migrations'
                );
            }

            // Do not load non migration files
            if ($this->module->runsMigrations && Str::endsWith($filePath, ['.php', '.php.stub'])) {
                $this->loadMigrationsFrom($filePath);
            }
        }
    }

    /**
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

        foreach (glob(database_path($migrationsPath.'*.php')) as $filename) {
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
     * @throws PcreException
     */
    private static function stripTimestampPrefix(string $filename): string
    {
        return preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
    }
}
