<?php

namespace Happenv\LaravelTrueModularModuleProvider;

use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\InitializeCallbacks;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessAssets;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessBladeComponents;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessBroadcasts;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessCommands;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessConfigs;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessEventListeners;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessInertia;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessLivewireComponents;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessMigrations;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessModelBuilderExtensions;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessModelExtensions;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessMorphMapDefinitions;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessPermissions;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessRoutes;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessSchedules;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessTranslations;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessViewComposers;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessViews;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessViewSharedData;
use Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider\ProcessVoters;
use Happenv\LaravelTrueModularModuleProvider\Exceptions\InvalidModule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider;
use Override;
use ReflectionClass;
use RuntimeException;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\PcreException;

abstract class ModuleProvider extends ServiceProvider
{
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
            ->processViewSharedData();

        if ($this->app->runningInConsole()) {
            $this
                ->processSchedules()
                ->processMigrations()
                ->processConsoleCommands();
        }

        $this->moduleBooted();

        return $this;
    }

    public function moduleBooted(): void {}

    protected function getModuleBaseDir(): string
    {
        $reflector = new ReflectionClass(static::class);

        $moduleBaseDir = dirname($reflector->getFileName());

        return $moduleBaseDir;
    }

    public function moduleView(?string $namespace): ?string
    {
        return is_null($namespace)
            ? $this->module->shortName()
            : $this->module->viewNamespace;
    }
}
