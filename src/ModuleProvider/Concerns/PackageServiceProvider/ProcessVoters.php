<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
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
