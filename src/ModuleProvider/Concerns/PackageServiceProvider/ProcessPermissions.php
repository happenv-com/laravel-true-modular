<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Webard\LaravelAccessControl\PermissionRegistry;

/**
 * @mixin ModuleProvider
 */
trait ProcessPermissions
{
    protected function processPermissions(): self
    {
        if (blank($this->module->permissions)) {
            return $this;
        }

        resolve(PermissionRegistry::class)
            ->register($this->module->permissions);

        return $this;
    }
}
