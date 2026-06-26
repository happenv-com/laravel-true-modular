<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Support\Str;

/**
 * @mixin ModuleProvider
 */
trait ProcessInertia
{
    protected function processInertia(): static
    {
        if (! $this->module->hasInertiaComponents) {
            return $this;
        }

        $namespace = $this->module->inertiaNamespace ?? $this->module->shortName();
        $directoryName = Str::of($namespace)->studly()->remove('-')->value();
        $vendorComponents = $this->module->vendorPath('resources/js/Pages');
        $appComponents = base_path('resources/js/Pages/'.$directoryName);

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$vendorComponents => $appComponents],
                $namespace.'-inertia-components'
            );
        }

        return $this;
    }
}
