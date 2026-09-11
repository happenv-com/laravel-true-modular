<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Tests\Doubles;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Happenv\LaravelTrueModular\ModuleSystem\PublishedMigrations;
use Override;

/**
 * A module whose migrations directory lives wherever the test put it, recording what
 * discovery hands to Laravel instead of letting it reach the publisher and the migrator.
 *
 * Both recorded calls are counted, not just merged: "one call per module" is the whole
 * point of batching them, and a per-file regression would leave the merged result
 * identical while quietly putting the cost back.
 */
final class RecordingMigrationModuleProvider extends ModuleProvider
{
    public static string $moduleBaseDir = '';

    /** @var list<array{paths: array<string, string>, groups: mixed}> */
    public array $publishCalls = [];

    /** @var list<array<string>|string> */
    public array $loadCalls = [];

    public bool $runsMigrations = true;

    #[Override]
    public function configureModule(Module $module): void
    {
        $module
            ->name('acme/catalog')
            ->discoversMigrations()
            ->runsMigrations($this->runsMigrations);
    }

    public function discoverMigrations(): void
    {
        $this->discoverModuleMigrations();
    }

    public function publishedMigrationsIndex(): PublishedMigrations
    {
        return $this->publishedMigrations();
    }

    #[Override]
    protected function getModuleBaseDir(): string
    {
        return self::$moduleBaseDir;
    }

    /**
     * @param  array<string, string>  $paths
     * @param  mixed  $groups
     */
    #[Override]
    protected function publishes(array $paths, $groups = null): void
    {
        $this->publishCalls[] = ['groups' => $groups, 'paths' => $paths];
    }

    /**
     * @param  array<string>|string  $paths
     */
    #[Override]
    protected function loadMigrationsFrom($paths): void
    {
        $this->loadCalls[] = $paths;
    }
}
