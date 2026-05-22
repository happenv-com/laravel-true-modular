<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;


trait ProcessModelExtensions
{
    protected function processModelExtensions(): self
    {
        if (blank($this->module->modelExtensions)) {
            return $this;
        }

        foreach ($this->module->modelExtensions as $model => $extension) {
            $this->registerAttributeResolver($model, $extension);

            $this->registerDynamicRelations($model, $extension);
        }

        return $this;
    }

    private function registerDynamicRelations(string $model, string $extension): void
    {
        $methods = (new \ReflectionClass($extension))->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->class !== $extension || $method->isConstructor()) {
                continue;
            }

            $methodName = $method->getName();
            $methodReturnType = $method->getReturnType();

            if (str_starts_with($methodName, 'get') && str_ends_with($methodName, 'Attribute')) {
                continue;
            }

            $model::resolveRelationUsing(
                $methodName,
                fn ($modelInstance) => new $extension($modelInstance)->{$methodName}()
            );
        }
    }

    private function registerAttributeResolver(string $model, string $extension): void
    {
        $firstExtensionForModel = ! AttributeResolversBag::has($model);

        AttributeResolversBag::addExtension($model, $extension);

        if ($firstExtensionForModel) {
            $model::handleMissingAttributeViolationUsing(
                fn ($modelInstance, $key) => AttributeResolversBag::resolve($modelInstance, $key)
            );
        }
    }
}
