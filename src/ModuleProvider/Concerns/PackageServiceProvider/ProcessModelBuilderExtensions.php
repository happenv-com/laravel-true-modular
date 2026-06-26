<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

trait ProcessModelBuilderExtensions
{
    protected function processModelBuilderExtensions(): self
    {
        if (blank($this->module->modelBuilderExtensions)) {
            return $this;
        }

        foreach ($this->module->modelBuilderExtensions as $builder => $extension) {
            assert(\class_exists($builder));
            $builder::mixin(new $extension);
        }

        return $this;
    }
}
