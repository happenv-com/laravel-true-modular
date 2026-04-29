<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * @mixin ModuleProvider
 */
trait ProcessViewSharedData
{
    /**
     * @throws RuntimeException
     */
    protected function processViewSharedData(): self
    {
        if (blank($this->module->sharedViewData)) {
            return $this;
        }

        foreach ($this->module->sharedViewData as $name => $value) {
            View::share($name, $value);
        }

        return $this;
    }
}
