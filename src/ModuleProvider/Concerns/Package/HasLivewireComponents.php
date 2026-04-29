<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModularModuleProvider\Module;
use InvalidArgumentException;

/**
 * @mixin Module
 */
trait HasLivewireComponents
{
    /**
     * @var array<string,class-string|null>
     */
    public array $livewireComponents = [];

    /**
     * @param  array<string,class-string>|string  $name
     * @param  class-string|null  $class
     *
     * @throws InvalidArgumentException
     */
    public function hasLivewireComponents(array | string $name, ?string $class = null): static
    {
        if (is_array($name)) {
            foreach ($name as $n => $class) {
                $this->hasLivewireComponents($n, $class);
            }

            return $this;
        }

        $this->livewireComponents[$name] = $class;

        return $this;
    }
}
