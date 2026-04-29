<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModularModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasViewSharedData
{
    /**
     * @var array<string,mixed>
     */
    public array $sharedViewData = [];

    public function sharesDataWithAllViews(string $name, mixed $value): static
    {
        $this->sharedViewData[$name] = $value;

        return $this;
    }
}
