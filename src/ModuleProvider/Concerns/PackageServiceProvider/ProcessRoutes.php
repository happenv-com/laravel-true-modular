<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessRoutes
{
    protected function processRoutes(): static
    {
        if (blank($this->module->routeFileNames)) {
            return $this;
        }

        foreach ($this->module->routeFileNames as $routeFileName) {
            $this->loadRoutesFrom($this->module->vendorPath('routes/'.$routeFileName.'.php'));
        }

        return $this;
    }
}
