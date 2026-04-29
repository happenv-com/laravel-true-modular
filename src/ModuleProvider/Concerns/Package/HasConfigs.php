<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasConfigs
{
    /**
     * @var string[]
     */
    public array $configs = [];

    /**
     * @var string[]
     */
    public array $configsToOverwrite = [];

    /**
     * @var array<int, array{0: string, 1: bool}>
     */
    public array $configsToExtend = [];

    /**
     * @var string[]
     */
    public array $configsToMerge = [];

    /**
     * @param  string|string[]  $config
     */
    public function hasConfig(array | string $config): static
    {
        if (! is_array($config)) {
            $config = [$config];
        }

        $this->configs = $config;

        return $this;
    }

    /**
     * @param  string|string[]  $configToMerge
     */
    public function mergesConfig(array | string $configToMerge): static
    {
        if (is_array($configToMerge)) {
            foreach ($configToMerge as $config) {
                $this->mergesConfig($config);
            }

            return $this;
        }

        $this->configsToMerge[] = $configToMerge;

        return $this;
    }

    /**
     * @param  string|string[]  $configToOverwrite
     */
    public function overwritesConfig(array | string $configToOverwrite): static
    {
        if (is_array($configToOverwrite)) {
            foreach ($configToOverwrite as $config) {
                $this->configsToOverwrite[] = $config;
            }

            return $this;
        }

        $this->configsToOverwrite[] = $configToOverwrite;

        return $this;
    }

    /**
     * @param  string|string[]  $configToExtend
     */
    public function extendsConfig(array | string $configToExtend, bool $overwrite = false): static
    {
        if (is_array($configToExtend)) {
            foreach ($configToExtend as $config) {
                $this->extendsConfig($config, $overwrite);
            }

            return $this;
        }

        $this->configsToExtend[] = [$configToExtend, $overwrite];

        return $this;
    }
}
