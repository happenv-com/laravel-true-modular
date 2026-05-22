<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Support\Facades\Config;
use Safe\Exceptions\FilesystemException;

use function Safe\realpath;

/**
 * @mixin ModuleProvider
 */
trait ProcessViews
{
    /**
     * @throws FilesystemException
     */
    protected function processViews(): self
    {
        if (! $this->module->hasViews) {
            return $this;
        }

        $namespace = $this->module->viewNamespace;
        $viewsPath = $this->module->basePath('/../resources/views');
        $vendorViews = realpath($viewsPath) ?: $viewsPath;
        $appViews = base_path('resources/views/vendor/'.$this->moduleView($namespace));

        $this->loadViewsFrom($vendorViews, $this->module->viewNamespace());

        if ($this->app->runningInConsole()) {
            $this->publishes([$vendorViews => $appViews], $this->moduleView($namespace).'-views');
        }

        return $this;
    }

    protected function processGlobalViews(): self
    {
        if (! $this->module->hasGlobalViews) {
            return $this;
        }

        Config::set('view.paths', array_merge(
            [$this->module->basePath('/../resources/views-global')],
            Config::get('view.paths', [])
        ));

        return $this;
    }
}
