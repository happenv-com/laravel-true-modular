<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessBladeComponents
{
    protected function processBladeComponents(): static
    {
        if (blank($this->module->viewComponents)) {
            return $this;
        }

        foreach ($this->module->viewComponents as $componentClass => $prefix) {
            $this->loadViewComponentsAs($prefix, [$componentClass]);
        }

        if ($this->app->runningInConsole()) {
            $vendorComponents = $this->module->basePath('/Components');
            $appComponents = base_path('app/View/Components/vendor/'.$this->module->shortName());

            $this->publishes([$vendorComponents => $appComponents], $this->module->name.'-components');
        }

        return $this;
    }
}
