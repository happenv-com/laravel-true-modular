<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModularModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasViews
{
    public bool $hasViews = false;

    public bool $hasGlobalViews = false;

    public ?string $viewNamespace = null;

    public function hasViews(?string $namespace = null): static
    {
        $this->hasViews = true;

        $this->viewNamespace = $namespace;

        return $this;
    }

    public function hasGlobalViews(): static
    {
        $this->hasGlobalViews = true;

        return $this;
    }

    public function viewNamespace(): string
    {
        return $this->viewNamespace ?? $this->shortName();
    }
}
