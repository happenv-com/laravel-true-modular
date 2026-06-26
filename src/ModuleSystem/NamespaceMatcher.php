<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

/**
 * Resolves which entry "owns" a fully-qualified class name by longest matching
 * namespace prefix. Shared by the service-provider sorter and the module locator,
 * which previously each carried their own copy of this matching loop.
 */
final class NamespaceMatcher
{
    /**
     * Return the value whose namespace key is the longest prefix of $subject,
     * or null when nothing matches.
     *
     * @template TValue
     *
     * @param  array<string, TValue>  $namespaceMap  namespace (trailing `\`) => value
     * @return TValue|null
     */
    public static function longestPrefix(string $subject, array $namespaceMap)
    {
        $best = null;
        $bestLength = -1;

        foreach ($namespaceMap as $namespace => $value) {
            $length = strlen($namespace);

            if ($length > $bestLength && str_starts_with($subject, $namespace)) {
                $best = $value;
                $bestLength = $length;
            }
        }

        return $best;
    }
}
