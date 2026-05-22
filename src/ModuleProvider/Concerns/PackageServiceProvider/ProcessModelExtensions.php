<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModelExtension\AttributeResolver;
use Happenv\LaravelTrueModular\ModelExtension\DynamicRelations;

trait ProcessModelExtensions
{
    protected function processModelExtensions(): self
    {
        if (blank($this->module->modelExtensions)) {
            return $this;
        }

        foreach ($this->module->modelExtensions as $model => $extension) {
            resolve(AttributeResolver::class)::register($model, $extension);
            resolve(DynamicRelations::class)::register($model, $extension);
        }

        return $this;
    }
}
