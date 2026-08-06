<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\Architecture\Renderer\DotRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\GraphTextRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\ImpactTextRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\JsonRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\MermaidRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\ModulesTextRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Renderer\TreeRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\WhyTextRenderer;
use Happenv\LaravelTrueModular\Architecture\Source\ComposerArchitectureSource;
use Happenv\LaravelTrueModular\Commands\ListModulesCommand;
use Happenv\LaravelTrueModular\Commands\MakeMigrationCommand;
use Happenv\LaravelTrueModular\Commands\MakeModuleCommand;
use Happenv\LaravelTrueModular\Commands\ModuleGraphCommand;
use Happenv\LaravelTrueModular\Commands\ModuleImpactCommand;
use Happenv\LaravelTrueModular\Commands\ModuleWhyCommand;
use Happenv\LaravelTrueModular\Commands\SeedModulesCommand;
use Happenv\LaravelTrueModular\Commands\SetupCommand;
use Happenv\LaravelTrueModular\Generators\ModuleGenerator;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleFileFinder;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Override;

class KernelServiceProvider extends ServiceProvider
{
    /**
     * Register the console commands in boot() — the standard lifecycle phase run
     * by every Application — rather than our custom initialize() phase, which
     * only fires under {@see Application}. Otherwise the commands (including
     * `true-modular:setup`, which swaps the app over) would be undiscoverable on
     * a fresh install still running the stock Illuminate Application.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupCommand::class,
                MakeModuleCommand::class,
                ListModulesCommand::class,
                SeedModulesCommand::class,
                MakeMigrationCommand::class,
                ModuleImpactCommand::class,
                ModuleWhyCommand::class,
                ModuleGraphCommand::class,
            ]);

            // Publishing the module stubs is how an application states its own
            // module convention: `module:make` reads the published directory
            // instead of the package's, and the stub SET decides which files a
            // new module gets. See {@see ModuleGenerator}.
            $this->publishes([
                ModuleGenerator::packageStubDirectory() => $this->app->basePath(ModuleGenerator::PUBLISHED_STUB_PATH),
            ], 'true-modular-stubs');
        }
    }

    #[Override]
    public function register(): void
    {
        $this->app->singletonIf(ModuleManifestRepository::class);

        $this->app->bind(ModuleGenerator::class, static fn (Application $app): ModuleGenerator => new ModuleGenerator(
            $app->make(Filesystem::class),
            $app->basePath(),
        ));

        $this->app->singleton(ModuleRegistry::class, static fn (): ModuleRegistry => ModuleRegistry::make());

        $this->app->singleton(ModuleFileFinder::class, static fn ($app): ModuleFileFinder => new ModuleFileFinder(
            $app->make(ModuleRegistry::class)
        ));

        $this->app->singleton(
            ModuleLocator::class,
            static fn (Application $app): AppModulesLocator => new AppModulesLocator(
                $app->make(ModuleRegistry::class),
                \Happenv\LaravelTrueModular\Application::getModulesVendor(),
            ),
        );

        $this->app->tag([ComposerArchitectureSource::class], 'architecture.sources');

        $this->app->bind(
            ArchitectureIndexBuilder::class,
            static fn (Application $app): ArchitectureIndexBuilder => new ArchitectureIndexBuilder(
                $app->tagged('architecture.sources'),
            ),
        );

        $this->app->tag([
            GraphTextRenderer::class,
            ImpactTextRenderer::class,
            WhyTextRenderer::class,
            ModulesTextRenderer::class,
            JsonRenderer::class,
            TreeRenderer::class,
            MermaidRenderer::class,
            DotRenderer::class,
        ], 'architecture.renderers');

        $this->app->singleton(
            RendererRegistry::class,
            static fn (Application $app): RendererRegistry => new RendererRegistry(
                $app->tagged('architecture.renderers'),
            ),
        );
    }
}
