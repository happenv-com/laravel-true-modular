<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessBroadcasts
{
    protected function processBroadcasts(): static
    {
        if (blank($this->module->broadcastFileNames)) {
            return $this;
        }

        foreach ($this->module->broadcastFileNames as $broadcastFileName) {
            require_once $this->module->vendorPath('routes/'.$broadcastFileName.'.php');
        }

        return $this;
    }
}
