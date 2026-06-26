<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelAccessControl\VoterRegistry;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessVoters
{
    protected function processVoters(): static
    {
        if (blank($this->module->voters)) {
            return $this;
        }

        resolve(VoterRegistry::class)->registerClass($this->module->voters);

        return $this;
    }
}
