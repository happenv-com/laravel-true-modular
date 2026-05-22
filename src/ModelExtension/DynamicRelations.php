<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModelExtension;

use Illuminate\Database\Eloquent\Relations\Relation;

class DynamicRelations
{
    /**
     * @param class-string $model
     * @param class-string $extension
     */
    public static function register(string $model, string $extension): void
    {
        $methods = (new \ReflectionClass($extension))->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->class !== $extension || $method->isConstructor()) {
                continue;
            }

            if (! self::isRelationMethod($method->getReturnType())) {
                continue;
            }

            $methodName = $method->getName();

            $model::resolveRelationUsing(
                $methodName,
                fn ($modelInstance) => new $extension($modelInstance)->{$methodName}()
            );
        }
    }

    private static function isRelationMethod(?\ReflectionType $returnType): bool
    {
        if (! $returnType instanceof \ReflectionNamedType) {
            return false;
        }

        $typeName = $returnType->getName();

        return class_exists($typeName)
            && is_a($typeName, Relation::class, true);
    }
}
