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
        $viewsPath = $this->module->vendorPath('resources/views');

        // Guard before Safe\realpath(): it throws on a non-existent path, which
        // would crash boot for a module that declares hasViews() but ships no
        // views directory (e.g. an empty, git-untracked dir on a fresh checkout).
        if ($this->moduleDirectoryMissing($viewsPath, 'views')) {
            return $this;
        }

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

        $globalViewsPath = $this->module->vendorPath('resources/views-global');

        if ($this->moduleDirectoryMissing($globalViewsPath, 'global views')) {
            return $this;
        }

        Config::set('view.paths', array_merge(
            [$globalViewsPath],
            Config::get('view.paths', [])
        ));

        return $this;
    }
}
