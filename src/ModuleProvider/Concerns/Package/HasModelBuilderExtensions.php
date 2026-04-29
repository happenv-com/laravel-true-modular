<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use InvalidArgumentException;
use Throwable;

/**
 * @mixin Module
 */
trait HasModelBuilderExtensions
{
    /**
     * @var array<class-string,class-string>
     */
    public array $modelBuilderExtensions = [];

    /**
     * @param  array<class-string,class-string>|class-string  $builder
     * @param  class-string|null  $extension
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function hasModelBuilderExtensions(array | string $builder, ?string $extension = null): static
    {
        if (is_array($builder)) {
            foreach ($builder as $b => $e) {
                $this->hasModelBuilderExtensions($b, $e);
            }

            return $this;
        }

        throw_if(! class_exists($builder), InvalidArgumentException::class, 'The builder class does not exist.');

        throw_if(! is_string($extension) || ! class_exists($extension), InvalidArgumentException::class, 'The extension class name for model builder extension must be a class-string.');

        $this->modelBuilderExtensions[$builder] = $extension;

        return $this;
    }
}
