<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\Support\MergesFlattened;
use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasHealthChecks
{
    use MergesFlattened;

    /**
     * @var string[]
     */
    public array $healthChecks = [];

    public function hasHealthCheck(string $checkClassName): static
    {
        $this->healthChecks[] = $checkClassName;

        return $this;
    }

    /**
     * @param  (string|string[])  ...$checkClassNames
     */
    public function hasHealthChecks(string|array ...$checkClassNames): static
    {
        $this->healthChecks = $this->mergeFlattened($this->healthChecks, $checkClassNames);

        return $this;
    }
}
