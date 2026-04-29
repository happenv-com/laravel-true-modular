<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModularModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasBladeComponents
{
    /**
     * @var string[]
     */
    public array $viewComponents = [];

    public function hasViewComponent(string $prefix, string $viewComponentName): static
    {
        $this->viewComponents[$viewComponentName] = $prefix;

        return $this;
    }

    public function hasViewComponents(string $prefix, string ...$viewComponentNames): static
    {
        foreach ($viewComponentNames as $componentName) {
            $this->viewComponents[$componentName] = $prefix;
        }

        return $this;
    }
}
