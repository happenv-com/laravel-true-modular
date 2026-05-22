<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModelExtension;

class AttributeResolver
{
    /**
     * @param class-string $model
     * @param class-string $extension
     */
    public static function register(string $model, string $extension): void
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
