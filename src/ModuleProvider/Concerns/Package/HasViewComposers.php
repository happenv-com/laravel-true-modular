<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasViewComposers
{
    /**
     * @var array<string, callable|class-string>
     */
    public array $viewComposers = [];

    /**
     * @param  string[]|string  $view
     * @param  callable|class-string  $viewComposer
     */
    public function hasViewComposer(array | string $view, callable | string $viewComposer): static
    {
        if (! is_array($view)) {
            $view = [$view];
        }

        foreach ($view as $viewName) {
            $this->viewComposers[$viewName] = $viewComposer;
        }

        return $this;
    }
}
