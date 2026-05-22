<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModelExtension;

use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;

class AttributeResolversBag
{
    /** @var array<class-string, list<ExtensionAttributeResolver>> */
    private static array $resolvers = [];

    private static ?object $sentinel = null;

    /** @param class-string $model */
    public static function addExtension(string $model, string $extension): void
    {
        self::$resolvers[$model][] = new ExtensionAttributeResolver($extension);
    }

    /** @param class-string $model */
    public static function has(string $model): bool
    {
        return isset(self::$resolvers[$model]);
    }

    public static function resolve(Model $model, string $key): mixed
    {
        $sentinel = self::sentinel();

        foreach (self::$resolvers[$model::class] ?? [] as $resolver) {
            $result = $resolver->resolve($model, $key, $sentinel);

            if ($result !== $sentinel) {
                return $result;
            }
        }

        throw new MissingAttributeException($model, $key);
    }

    public static function flush(): void
    {
        self::$resolvers = [];
        self::$sentinel = null;
    }

    private static function sentinel(): object
    {
        return self::$sentinel ??= new class {};
    }
}
