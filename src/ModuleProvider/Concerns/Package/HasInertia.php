<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasInertia
{
    public bool $hasInertiaComponents = false;

    /** Namespace for Inertia pages; independent of the Blade view namespace. */
    public ?string $inertiaNamespace = null;

    public function hasInertiaComponents(?string $namespace = null): static
    {
        $this->hasInertiaComponents = true;

        $this->inertiaNamespace = $namespace;

        return $this;
    }
}
