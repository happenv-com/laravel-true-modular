<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessTranslations
{
    protected function processTranslations(): static
    {
        if (! $this->module->hasTranslations) {
            return $this;
        }

        $vendorTranslations = $this->module->vendorPath('resources/lang');
        $appTranslations = (function_exists('lang_path'))
            ? lang_path('vendor/'.$this->module->shortName())
            : resource_path('lang/vendor/'.$this->module->shortName());

        $this->loadTranslationsFrom($vendorTranslations, $this->module->shortName());

        $this->loadJsonTranslationsFrom($vendorTranslations);
        $this->loadJsonTranslationsFrom($appTranslations);

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$vendorTranslations => $appTranslations],
                $this->module->shortName().'-translations'
            );
        }

        return $this;
    }
}
