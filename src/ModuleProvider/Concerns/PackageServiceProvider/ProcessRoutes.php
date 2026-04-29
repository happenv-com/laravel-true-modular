<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessRoutes
{
    protected function processRoutes(): self
    {
        if (blank($this->module->routeFileNames)) {
            return $this;
        }

        foreach ($this->module->routeFileNames as $routeFileName) {
            $this->loadRoutesFrom(
                sprintf(
                    '%s%s.php',
                    $this->module->basePath('/../routes/'),
                    $routeFileName
                )
            );
        }

        return $this;
    }
}
