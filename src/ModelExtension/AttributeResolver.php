<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModelExtension;

use Illuminate\Database\Eloquent\Model;

class AttributeResolver
{
    /**
     * @param  class-string  $model
     * @param  class-string  $extension
     */
    public static function register(string $model, string $extension): void
    {
        $firstExtensionForModel = ! AttributeResolversBag::has($model);

        AttributeResolversBag::addExtension($model, $extension);

        if ($firstExtensionForModel) {
            $model::handleMissingAttributeViolationUsing(
                fn (Model $modelInstance, string $key): mixed => AttributeResolversBag::resolve($modelInstance, $key)
            );
        }
    }
}
