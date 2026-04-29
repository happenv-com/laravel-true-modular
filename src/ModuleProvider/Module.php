<?php

namespace Happenv\LaravelTrueModularModuleProvider;

use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasAssets;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasBladeComponents;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasBroadcasts;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasCommands;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasConfigs;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasEventListeners;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasInertia;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasLivewireComponents;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasMigrations;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasModelBuilderExtensions;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasModelExtensions;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasMorphMapDefinitions;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasPermissions;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasRoutes;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasSchedule;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasServiceProviders;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasTranslations;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasViewComposers;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasViews;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasViewSharedData;
use Happenv\LaravelTrueModularModuleProvider\Concerns\Package\HasVoters;
use Illuminate\Support\Str;

class Module
{
    use HasAssets;
    use HasBladeComponents;
    use HasBroadcasts;
    use HasCommands;
    use HasConfigs;
    use HasEventListeners;
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

        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($directory, DIRECTORY_SEPARATOR);
    }

    public function setBasePath(string $path): static
    {
        $this->basePath = $path;

        return $this;
    }
}
