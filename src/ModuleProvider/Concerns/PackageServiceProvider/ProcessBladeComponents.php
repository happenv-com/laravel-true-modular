<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessBladeComponents
{
    protected function processBladeComponents(): self
    {
        if (blank($this->module->viewComponents)) {
            return $this;
        }

        foreach ($this->module->viewComponents as $componentClass => $prefix) {
            $this->loadViewComponentsAs($prefix, [$componentClass]);
        }

        if ($this->app->runningInConsole()) {
            $vendorComponents = $this->module->basePath('/Components');
            $appComponents = base_path('app/View/Components/vendor/' . $this->module->shortName());

            $this->publishes([$vendorComponents => $appComponents], $this->module->name . '-components');
        }

        return $this;
    }
}
