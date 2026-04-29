<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Livewire\Livewire;
use RuntimeException;

/**
 * @mixin ModuleProvider
 */
trait ProcessLivewireComponents
{
    /**
     * @throws RuntimeException
     */
    protected function processLivewireComponents(): self
    {
        if (blank($this->module->livewireComponents)) {
            return $this;
        }

        foreach ($this->module->livewireComponents as $name => $class) {
            Livewire::component($name, $class);
        }

        return $this;
    }
}
