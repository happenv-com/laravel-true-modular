<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Webard\LaravelAccessControl\PermissionRegistry;

/**
 * @mixin ModuleProvider
 */
trait ProcessPermissions
{
    protected function processPermissions(): static
    {
        if (blank($this->module->permissions)) {
            return $this;
        }

        resolve(PermissionRegistry::class)
            ->register($this->module->permissions);

        return $this;
    }
}
