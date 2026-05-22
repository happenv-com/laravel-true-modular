<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;

class AttributeResolversBag
{
    /** @var array<class-string, list<callable>> */
    private static array $resolvers = [];

    private static mixed $notFound = null;

    public static function add(string $model, callable $resolver): void
    {
        self::$resolvers[$model][] = $resolver;
    }

    public static function has(string $model): bool
    {
        return isset(self::$resolvers[$model]);
    }

    public static function resolve(Model $model, string $key): mixed
    {
        $sentinel = self::sentinel();

        foreach (self::$resolvers[$model::class] ?? [] as $resolver) {
            $result = $resolver($model, $key, $sentinel);

            if ($result !== $sentinel) {
                return $result;
            }
        }

        throw new MissingAttributeException($model, $key);
    }

    private static function sentinel(): object
    {
        if (self::$notFound === null) {
            self::$notFound = new class {};
        }

        return self::$notFound;
    }

    public static function flush(): void
    {
        self::$resolvers = [];
        self::$notFound = null;
    }
}
