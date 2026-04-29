<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModularModuleProvider\ModuleProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @mixin ModuleProvider
 */
trait ProcessMorphMapDefinitions
{
    protected function processMorphMapDefinitions(): self
    {
        if (blank($this->module->morphMapDefinitions)) {
            return $this;
        }

        Relation::morphMap($this->module->morphMapDefinitions);

        return $this;
    }
}
