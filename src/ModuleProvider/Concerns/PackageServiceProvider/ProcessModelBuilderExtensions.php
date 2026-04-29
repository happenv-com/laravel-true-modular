<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider;

use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionMethod;

trait ProcessModelBuilderExtensions
{
    protected function processModelBuilderExtensions(): self
    {
        if (blank($this->module->modelBuilderExtensions)) {
            return $this;
        }

        foreach ($this->module->modelBuilderExtensions as $builder => $extension) {
            $reflection = new ReflectionClass($extension);

            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                if ($method->class !== $extension) {
                    continue;
                }

                if ($method->isConstructor()) {
                    continue;
                }

                $methodName = $method->getName();

                /**
                 * @var class-string<EloquentQueryBuilder<Model>> $builder
                 */
                assert(\class_exists($builder));

                $builder::macro(
                    $methodName,
                    fn (...$args) => new $extension()->{$methodName}(...$args)
                );
            }
        }

        return $this;
    }
}
