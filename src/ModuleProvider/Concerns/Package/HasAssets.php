<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModularModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasAssets
{
    public bool $hasAssets = false;

    public function hasAssets(): static
    {
        $this->hasAssets = true;

        return $this;
    }
}
