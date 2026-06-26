<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Support\Str;

/**
 * @mixin ModuleProvider
 */
trait ProcessInertia
{
    protected function processInertia(): self
    {
        if (! $this->module->hasInertiaComponents) {
            return $this;
        }

        $namespace = $this->module->viewNamespace;
        $directoryName = Str::of($this->moduleView($namespace))->studly()->remove('-')->value();
        $vendorComponents = $this->module->vendorPath('resources/js/Pages');
        $appComponents = base_path('resources/js/Pages/'.$directoryName);

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$vendorComponents => $appComponents],
                $this->moduleView($namespace).'-inertia-components'
            );
        }

        return $this;
    }
}
