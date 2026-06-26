<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessAssets
{
    protected function processAssets(): static
    {
        if (! $this->module->hasAssets || ! $this->app->runningInConsole()) {
            return $this;
        }

        $vendorAssets = $this->module->vendorPath('resources/dist');
        $appAssets = public_path('vendor/'.$this->module->shortName());

        $this->publishes([$vendorAssets => $appAssets], $this->module->shortName().'-assets');

        return $this;
    }
}
