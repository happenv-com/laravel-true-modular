<?php

namespace Happenv\LaravelTrueModular\ModuleProvider;

use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\GuardsModulePaths;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\InitializeCallbacks;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessAssets;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessBladeComponents;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessBroadcasts;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessCommands;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessConfigs;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessEventListeners;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessInertia;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessLivewireComponents;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessMigrations;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessModelBuilderExtensions;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessModelExtensions;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessMorphMapDefinitions;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessPermissions;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessRoutes;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessSchedules;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessTranslations;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessViewComposers;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessViews;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessViewSharedData;
use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\ProcessVoters;
use Happenv\LaravelTrueModular\ModuleProvider\Exceptions\InvalidModule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Traits\Macroable;
use Override;
use ReflectionClass;
use RuntimeException;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\PcreException;

abstract class ModuleProvider extends ServiceProvider
{
    use Macroable;
    use GuardsModulePaths;
    use InitializeCallbacks;
    use ProcessAssets;
    use ProcessBladeComponents;
    use ProcessBroadcasts;
    use ProcessCommands;
    use ProcessConfigs;
    use ProcessEventListeners;
    use ProcessInertia;
    use ProcessLivewireComponents;
    use ProcessMigrations;
    use ProcessModelBuilderExtensions;
    use ProcessModelExtensions;
    use ProcessMorphMapDefinitions;
    use ProcessPermissions;
    use ProcessRoutes;
    use ProcessSchedules;
    use ProcessTranslations;
    use ProcessViewComposers;
    use ProcessViews;
    use ProcessViewSharedData;
    use ProcessVoters;

    protected Module $module;

    abstract public function configureModule(Module $module): void;

    /** @throws InvalidModule */
    #[Override]
    final public function register(): ModuleProvider
    {
        $this->registeringModule();

        $this->module = $this->newModule();
        $this->module->setBasePath($this->getModuleBaseDir());

        $this->configureModule($this->module);
        if ((! isset($this->module->name) || ($this->module->name === '' || $this->module->name === '0'))) {
            throw InvalidModule::nameIsRequired();
        }

        $this->moduleRegistered();

        return $this;
    }

    public function registeringModule(): void {}

    public function newModule(): Module
    {
        return new Module;
    }

    public function moduleRegistered(): void {}

    public function initializingModule(): void {}

    /**
     * @throws BindingResolutionException
     * @throws RuntimeException
     */
    final public function initialize(): ModuleProvider
    {
        $this->initializingModule();

        if (! ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
            $this
                ->overwriteConfigs()
                ->mergeConfigs()
                ->extendConfigs()
                ->processConfigs();
        }

        $this
            ->processModelExtensions()
            ->processModelBuilderExtensions()
            ->processMorphMapDefinitions()
            ->processPermissions()
            ->processVoters()
            ->processGlobalViews()
            ->processEventListeners()
            ->processLivewireComponents();

        $this->moduleInitialized();

        return $this;
    }

    public function moduleInitialized(): void {}

    public function bootingModule(): void {}

    /**
     * @throws FilesystemException
     * @throws PcreException
     * @throws RuntimeException
     */
    final public function boot(): ModuleProvider
    {
        $this->bootingModule();

        $this
            ->processBroadcasts()
            ->processAssets()
            ->processBladeComponents()
            ->processCommands()
            ->processInertia()
            ->processRoutes()
            ->processTranslations()
            ->processViews()
            ->processViewComposers()
            ->processViewSharedData()
            ->processMigrations();

        if ($this->app->runningInConsole()) {
            $this
                ->processSchedules()
                ->processConsoleCommands();
        }

        $this->moduleBooted();

        return $this;
    }

    public function moduleBooted(): void {}

    protected function getModuleBaseDir(): string
    {
        $reflector = new ReflectionClass(static::class);

        return dirname($reflector->getFileName());
    }

    public function moduleView(?string $namespace): ?string
    {
        return is_null($namespace)
            ? $this->module->shortName()
            : $this->module->viewNamespace;
    }
}
