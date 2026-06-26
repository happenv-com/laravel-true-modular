<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Exceptions\InvalidModule;
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
     * Morph map keys are prefixed with the module name (`{name}::{key}`), so the
     * module must be named first. Calling this before `name()` throws a clear
     * error instead of a raw "uninitialized property" fatal.
     *
     * @param  array<string,class-string>|string  $key
     * @param  class-string|null  $class
     *
     * @throws InvalidArgumentException
     * @throws InvalidModule when called before `name()`
     * @throws Throwable
     */
    public function hasMorphMap(array|string $key, ?string $class = null): static
    {
        if (is_array($key)) {
            foreach ($key as $k => $class) {
                $this->hasMorphMap($k, $class);
            }

            return $this;
        }

        throw_unless(is_string($class), InvalidArgumentException::class, 'The class name for morph map definition must be a class-string.');

        if (! isset($this->name)) {
            throw InvalidModule::nameRequiredFor('hasMorphMap');
        }

        $this->morphMapDefinitions[$this->name.'::'.$key] = $class;

        return $this;
    }
}
