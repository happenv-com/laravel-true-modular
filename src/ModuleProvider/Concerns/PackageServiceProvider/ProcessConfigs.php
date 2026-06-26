<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\Config\ConfigMerger;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @mixin ModuleProvider
 */
trait ProcessConfigs
{
    /**
     * @throws BindingResolutionException
     */
    public function overwriteConfigs(): self
    {
        if (blank($this->module->configsToOverwrite)) {
            return $this;
        }

        $config = $this->app->make('config');

        foreach ($this->module->configsToOverwrite as $configToOverwrite) {
            $vendorConfig = $this->module->vendorPath('config/'.$this->normalizeConfigPath($configToOverwrite).'.php');

            // $this->replaceConfigRecursivelyFrom($vendorConfig, $configToOverwrite);

            $config->set($configToOverwrite, array_replace_recursive(
                $config->get($configToOverwrite, []),
                require $vendorConfig
            ));
        }

        return $this;
    }

    /**
     * @throws BindingResolutionException
     */
    public function processConfigs(): self
    {
        if (blank($this->module->configs)) {
            return $this;
        }

        $config = $this->app->make('config');

        foreach ($this->module->configs as $configFileName) {

            $vendorConfig = $this->module->vendorPath('config/'.$this->normalizeConfigPath($configFileName).'.php');

            $config->set($this->normalizeConfigKey($this->module->shortName().'::'.$configFileName), require $vendorConfig);
        }

        return $this;
    }

    public function mergeConfigs(): self
    {
        if (blank($this->module->configsToMerge)) {
            return $this;
        }

        foreach ($this->module->configsToMerge as $configFileName) {

            $vendorConfig = $this->module->vendorPath('config/'.$this->normalizeConfigPath($configFileName).'.php');

            $this->mergeConfigFrom($vendorConfig, $configFileName);
        }

        return $this;
    }

    /**
     * @throws BindingResolutionException
     */
    public function extendConfigs(): self
    {
        if (blank($this->module->configsToExtend)) {
            return $this;
        }

        foreach ($this->module->configsToExtend as $configToExtend) {
            [$configFileName, $overwrite] = $configToExtend;

            $vendorConfig = $this->module->vendorPath('config/'.$this->normalizeConfigPath($configFileName).'.php');

            $this->mergeRecursiveConfigFrom($vendorConfig, $configFileName, $overwrite);
        }

        return $this;
    }

    /**
     * @throws BindingResolutionException
     */
    protected function mergeRecursiveConfigFrom(string $path, string $key, bool $overwrite = false): void
    {
        $config = $this->app->make('config');

        $loadedConfig = require $path;

        $config->set($key, (new ConfigMerger)->merge(
            $config->get($key, []),
            $loadedConfig,
            $overwrite,
        ));
    }

    protected function normalizeConfigKey(string $configFileName): string
    {
        return str_replace(['/', '\\'], '.', $configFileName);
    }

    protected function normalizeConfigPath(string $configFileName): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configFileName);
    }
}
