<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use InvalidArgumentException;
use Throwable;

/**
 * @mixin Module
 */
trait HasModelExtensions
{
    /**
     * @var array<class-string,class-string>
     */
    public array $modelExtensions = [];

    /**
     * @param  array<class-string,class-string>|class-string  $model
     * @param  class-string|null  $extension
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function hasModelExtensions(array|string $model, ?string $extension = null): static
    {
        if (is_array($model)) {
            foreach ($model as $m => $e) {
                $this->hasModelExtensions($m, $e);
            }

            return $this;
        }

        throw_if(! class_exists($model), InvalidArgumentException::class, 'The model class does not exist.');

        throw_if(! is_string($extension) || ! class_exists($extension), InvalidArgumentException::class, 'The extension class name for model extension must be a class-string.');

        $this->modelExtensions[$model] = $extension;

        return $this;
    }
}
