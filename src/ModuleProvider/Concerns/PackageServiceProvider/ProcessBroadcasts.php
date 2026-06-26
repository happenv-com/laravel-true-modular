<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessBroadcasts
{
    protected function processBroadcasts(): self
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
