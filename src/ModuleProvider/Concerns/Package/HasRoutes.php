<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasRoutes
{
    /**
     * @var string[]
     */
    public array $routeFileNames = [];

    public function hasRoute(string $routeFileName): static
    {
        $this->routeFileNames[] = $routeFileName;

        return $this;
    }

    public function hasRoutes(string ...$routeFileNames): static
    {
        $this->routeFileNames = array_merge(
            $this->routeFileNames,
            collect($routeFileNames)->flatten()->toArray()
        );

        return $this;
    }
}
