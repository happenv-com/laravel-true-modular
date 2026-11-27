<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModelExtension;

use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;

/**
 * Process-wide registry of attribute resolvers keyed by model class. State is
 * static because resolution is triggered from Eloquent's missing-attribute hook,
 * which has no access to the container — {@see flush()} exists so tests can reset it.
 *
 * A process can boot the application more than once — every test of an application
 * that uses this package does — and each boot registers every model extension again.
 * A registration is therefore keyed by its extension and a repeated one changes
 * nothing. Appended instead, the registry grew by one resolver per extension per
 * boot (55 per boot in one application, ~11 KB per test, never released), and
 * {@see resolve()} built every copy for each attribute no extension provides.
 *
 * The sentinel is a unique object used to distinguish "an extension resolved this
 * attribute to null" from "no extension provided this attribute"; comparing against
 * null would conflate the two.
 */
class AttributeResolversBag
{
    /** @var array<class-string, array<class-string, ExtensionAttributeResolver>> */
    private static array $resolvers = [];

    private static ?object $sentinel = null;

    /**
     * @param  class-string  $model
     * @param  class-string  $extension
     */
    public static function addExtension(string $model, string $extension): void
    {
        self::$resolvers[$model][$extension] ??= new ExtensionAttributeResolver($extension);
    }

    /** @param class-string $model */
    public static function has(string $model): bool
    {
        return isset(self::$resolvers[$model]);
    }

    /**
     * The extensions registered for $model, in the order {@see resolve()} asks them.
     *
     * When more than one of them provides an attribute, the first one listed is the
     * one that answers. A repeated registration keeps the extension's first position.
     * Static analysis reads this to type an attribute from the accessor that really runs.
     *
     * @param  class-string  $model
     * @return list<class-string>
     */
    public static function extensionsFor(string $model): array
    {
        return array_keys(self::$resolvers[$model] ?? []);
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
