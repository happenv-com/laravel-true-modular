<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModularModuleProvider\ModuleProvider;
use Webard\LaravelAccessControl\VoterRegistry;

/**
 * @mixin ModuleProvider
 */
trait ProcessVoters
{
    protected function processVoters(): self
    {
        if (blank($this->module->voters)) {
            return $this;
        }

        resolve(VoterRegistry::class)->registerClass($this->module->voters);

        return $this;
    }
}
