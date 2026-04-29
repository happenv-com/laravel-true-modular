<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModularModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasInertia
{
    public bool $hasInertiaComponents = false;

    public function hasInertiaComponents(?string $namespace = null): static
    {
        $this->hasInertiaComponents = true;

        $this->viewNamespace = $namespace;

        return $this;
    }
}
