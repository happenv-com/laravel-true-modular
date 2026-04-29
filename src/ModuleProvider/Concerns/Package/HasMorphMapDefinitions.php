<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use InvalidArgumentException;
use Throwable;

/**
 * @mixin Module
 */
trait HasMorphMapDefinitions
{
    /**
     * @var array<string,class-string>
     */
    public array $morphMapDefinitions = [];

    /**
     * @param  array<string,class-string>|string  $key
     * @param  class-string|null  $class
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function hasMorphMap(array | string $key, ?string $class = null): static
    {
        if (is_array($key)) {
            foreach ($key as $k => $class) {
                $this->hasMorphMap($k, $class);
            }

            return $this;
        }

        throw_unless(is_string($class), InvalidArgumentException::class, 'The class name for morph map definition must be a class-string.');

        $this->morphMapDefinitions[$this->name . '::' . $key] = $class;

        return $this;
    }
}
