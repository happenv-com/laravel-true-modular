<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModularModuleProvider\ModuleProvider;

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
            require_once $this->module->basePath(sprintf('/../routes/%s.php', $broadcastFileName));
        }

        return $this;
    }
}
