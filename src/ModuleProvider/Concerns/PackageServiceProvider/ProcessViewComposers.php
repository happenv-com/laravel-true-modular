<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModularModuleProvider\ModuleProvider;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * @mixin ModuleProvider
 */
trait ProcessViewComposers
{
    /**
     * @throws RuntimeException
     */
    protected function processViewComposers(): self
    {
        if (blank($this->module->viewComposers)) {
            return $this;
        }

        foreach ($this->module->viewComposers as $viewName => $viewComposer) {
            View::composer($viewName, $viewComposer);
        }

        return $this;
    }
}
