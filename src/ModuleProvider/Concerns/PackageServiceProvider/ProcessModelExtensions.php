<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;

trait ProcessModelExtensions
{
    protected function processModelExtensions(): self
    {
        if (blank($this->module->modelExtensions)) {
            return $this;
        }

        foreach ($this->module->modelExtensions as $model => $extension) {
            $reflection = new ReflectionClass($extension);

            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            $firstExtensionForModel = ! AttributeResolversBag::has($model);

            AttributeResolversBag::add($model, fn ($modelInstance, $key, $notFound) => $this->resolveAttribute($extension, $modelInstance, $key, $notFound));

            if ($firstExtensionForModel) {
                $model::handleMissingAttributeViolationUsing(
                    fn ($modelInstance, $key) => AttributeResolversBag::resolve($modelInstance, $key)
                );
            }

            foreach ($methods as $method) {
                if ($method->class !== $extension) {
                    continue;
                }

                if ($method->isConstructor()) {
                    continue;
                }

                $methodName = $method->getName();

                /** @var class-string<Model> $model */
                assert(\class_exists($model));

                if (str_starts_with($methodName, 'get') && str_ends_with($methodName, 'Attribute')) {
                    continue;
                }

                $model::resolveRelationUsing(
                    $methodName,
                    fn ($modelInstance) => new $extension($modelInstance)->{$methodName}()
                );
            }
        }

        return $this;
    }

    private function resolveAttribute(string $extension, Model $model, string $key, mixed $notFound): mixed
    {
        $methodName = 'get' . Str::studly($key) . 'Attribute';

        $instance = new $extension($model);

        if (method_exists($instance, $methodName)) {
            return $instance->{$methodName}();
        }

        return $notFound;
    }
}
