<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider;

use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasAssets;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasBladeComponents;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasBroadcasts;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasCommands;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasConfigs;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasEventListeners;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasHealthChecks;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasInertia;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasLivewireComponents;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasMigrations;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasModelBuilderExtensions;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasModelExtensions;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasMorphMapDefinitions;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasPermissions;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasRoutes;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasSchedule;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasServiceProviders;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasTranslations;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasViewComposers;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasViews;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasViewSharedData;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\HasVoters;
use Illuminate\Support\Str;

class Module
{
    use HasAssets;
    use HasBladeComponents;
    use HasBroadcasts;
    use HasCommands;
    use HasConfigs;
    use HasEventListeners;
    use HasHealthChecks;
    use HasInertia;
    use HasLivewireComponents;
    use HasMigrations;
    use HasModelBuilderExtensions;
    use HasModelExtensions;
    use HasMorphMapDefinitions;
    use HasPermissions;
    use HasRoutes;
    use HasSchedule;
    use HasServiceProviders;
    use HasTranslations;
    use HasViewComposers;
    use HasViews;
    use HasViewSharedData;
    use HasVoters;

    public string $name;

    public string $basePath;

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function shortName(): string
    {
        return Str::after($this->name, 'laravel-');
    }

    public function basePath(?string $directory = null): string
    {
        if ($directory === null) {
            return $this->basePath;
        }

        return $this->basePath.DIRECTORY_SEPARATOR.ltrim($directory, DIRECTORY_SEPARATOR);
    }

    public function setBasePath(string $path): static
    {
        $this->basePath = $path;

        return $this;
    }

    /**
     * Resolve a path inside the module package root (one level above the
     * provider's `src/` directory), where shipped resources live:
     * `config/`, `routes/`, `database/`, `resources/`, etc.
     */
    public function vendorPath(string $relative = ''): string
    {
        return $this->basePath('/../'.ltrim($relative, '/'));
    }
}
