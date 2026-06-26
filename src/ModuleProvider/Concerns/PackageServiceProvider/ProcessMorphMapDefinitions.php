<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @mixin ModuleProvider
 */
trait ProcessMorphMapDefinitions
{
    protected function processMorphMapDefinitions(): static
    {
        if (blank($this->module->morphMapDefinitions)) {
            return $this;
        }

        Relation::morphMap($this->module->morphMapDefinitions);

        return $this;
    }
}
