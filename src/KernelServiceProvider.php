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
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Renderer\TreeRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\WhyTextRenderer;
use Happenv\LaravelTrueModular\Architecture\Source\ComposerArchitectureSource;
use Happenv\LaravelTrueModular\Commands\ListModulesCommand;
use Happenv\LaravelTrueModular\Commands\MakeMigrationCommand;
use Happenv\LaravelTrueModular\Commands\ModuleGraphCommand;
use Happenv\LaravelTrueModular\Commands\ModuleImpactCommand;
use Happenv\LaravelTrueModular\Commands\ModuleWhyCommand;
use Happenv\LaravelTrueModular\Commands\SeedModulesCommand;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleFileFinder;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

class KernelServiceProvider extends ServiceProvider
{
    public function initialize(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListModulesCommand::class,
                SeedModulesCommand::class,
                MakeMigrationCommand::class,
                ModuleImpactCommand::class,
                ModuleWhyCommand::class,
                ModuleGraphCommand::class,
            ]);
        }
    }

    #[Override]
    public function register(): void
    {
        $this->app->singleton(ModuleTree::class, static fn (): ModuleTree => ModuleTree::make());

        $this->app->singleton(ModuleFileFinder::class, static fn ($app): ModuleFileFinder => new ModuleFileFinder(
            $app->make(ModuleTree::class)
        ));

        $this->app->singleton(
            ModuleLocator::class,
            static fn (Application $app): AppModulesLocator => new AppModulesLocator($app->make(ModuleTree::class)),
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
