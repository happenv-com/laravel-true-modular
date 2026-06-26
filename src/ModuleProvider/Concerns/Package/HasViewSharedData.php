<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;

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
