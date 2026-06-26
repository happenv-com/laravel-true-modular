<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Exceptions\InvalidModule;
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
     * Declare one or more config files shipped by the module. Configs are scoped
     * per module (published under `{shortName}::{file}`), so declaring the same
     * config twice is a mistake and throws rather than silently overwriting.
     *
     * @param  string|string[]  $config
     *
     * @throws InvalidModule when a config file is declared more than once
     */
    public function hasConfig(array|string $config): static
    {
        foreach (is_array($config) ? $config : [$config] as $name) {
            if (in_array($name, $this->configs, strict: true)) {
                throw InvalidModule::configAlreadyRegistered($name);
            }

            $this->configs[] = $name;
        }

        return $this;
    }

    /**
     * @param  string|string[]  $configToMerge
     */
    public function mergesConfig(array|string $configToMerge): static
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
    public function overwritesConfig(array|string $configToOverwrite): static
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
    public function extendsConfig(array|string $configToExtend, bool $overwrite = false): static
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
