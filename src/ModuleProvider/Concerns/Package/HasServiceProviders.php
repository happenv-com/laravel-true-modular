<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasServiceProviders
{
    public ?string $publishableProviderName = null;

    public function publishesServiceProvider(string $providerName): static
    {
        $this->publishableProviderName = $providerName;

        return $this;
    }
}
