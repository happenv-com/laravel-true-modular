<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModularModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessTranslations
{
    protected function processTranslations(): self
    {
        if (! $this->module->hasTranslations) {
            return $this;
        }

        $vendorTranslations = $this->module->basePath('/../resources/lang');
        $appTranslations = (function_exists('lang_path'))
            ? lang_path('vendor/' . $this->module->shortName())
            : resource_path('lang/vendor/' . $this->module->shortName());

        $this->loadTranslationsFrom($vendorTranslations, $this->module->shortName());

        $this->loadJsonTranslationsFrom($vendorTranslations);
        $this->loadJsonTranslationsFrom($appTranslations);

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$vendorTranslations => $appTranslations],
                $this->module->shortName() . '-translations'
            );
        }

        return $this;
    }
}
