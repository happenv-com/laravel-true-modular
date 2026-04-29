<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
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
