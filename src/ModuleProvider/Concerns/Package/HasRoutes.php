<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\Support\MergesFlattened;
use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasRoutes
{
    use MergesFlattened;

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
        $this->routeFileNames = $this->mergeFlattened($this->routeFileNames, $routeFileNames);

        return $this;
    }
}
