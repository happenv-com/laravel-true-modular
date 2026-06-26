<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\Support\MergesFlattened;
use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasMigrations
{
    use MergesFlattened;

    public bool $runsMigrations = false;

    public bool $discoversMigrations = false;

    public ?string $migrationsPath = null;

    /**
     * @var string[]
     */
    public array $migrationFileNames = [];

    public function runsMigrations(bool $runsMigrations = true): static
    {
        $this->runsMigrations = $runsMigrations;

        return $this;
    }

    public function hasMigration(string $migrationFileName): static
    {
        $this->migrationFileNames[] = $migrationFileName;

        return $this;
    }

    public function hasMigrations(string ...$migrationFileNames): static
    {
        $this->migrationFileNames = $this->mergeFlattened($this->migrationFileNames, $migrationFileNames);

        return $this;
    }

    public function discoversMigrations(bool $discoversMigrations = true, string $path = '/database/migrations'): static
    {
        $this->discoversMigrations = $discoversMigrations;
        $this->migrationsPath = $path;

        return $this;
    }
}
