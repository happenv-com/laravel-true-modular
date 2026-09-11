<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

/**
 * Resolves which entry "owns" a fully-qualified class name by longest matching
 * namespace prefix. Shared by the service-provider sorter and the module locator,
 * which previously each carried their own copy of this matching loop.
 *
 * Only a prefix of the subject can ever win, and a prefix is fully determined by its
 * length — so the answer never depends on the keys that are not prefixes, and the map
 * does not have to be walked to find it. Indexing the keys by length turns a lookup
 * into a handful of hash probes: on a host with 88 modules the provider sorter went
 * from ~70 000 string comparisons per boot to ~4 000 probes, on a path that runs on
 * every single boot of the application.
 *
 * The index is keyed by LENGTH and not by namespace segment on purpose. The module
 * locator matches filesystem paths through the same class, and those are separated by
 * `/`, not `\`; a segment walk would be a shade faster and quietly wrong for half the
 * callers.
 *
 * @template TValue
 */
final readonly class NamespaceMatcher
{
    /**
     * @param  array<string, TValue>  $namespaceMap  namespace (trailing `\`) => value
     * @param  list<int>  $lengths  distinct key lengths, longest first
     */
    private function __construct(
        private array $namespaceMap,
        private array $lengths,
    ) {}

    /**
     * Index a namespace map for repeated lookups.
     *
     * Callers resolving many subjects against one map — the provider sorter asks once
     * per registered provider — should build this once and keep it; building it is the
     * single pass over the map that `match()` then never has to repeat.
     *
     * @template TIndexed
     *
     * @param  array<string, TIndexed>  $namespaceMap  namespace (trailing `\`) => value
     * @return self<TIndexed>
     */
    public static function for(array $namespaceMap): self
    {
        $lengths = [];

        foreach (array_keys($namespaceMap) as $namespace) {
            $lengths[strlen((string) $namespace)] = true;
        }

        $lengths = array_keys($lengths);
        rsort($lengths);

        return new self($namespaceMap, $lengths);
    }

    /**
     * Return the value whose namespace key is the longest prefix of $subject,
     * or null when nothing matches.
     *
     * @return TValue|null
     */
    public function match(string $subject)
    {
        $subjectLength = strlen($subject);

        foreach ($this->lengths as $length) {
            if ($length > $subjectLength) {
                continue;
            }

            $prefix = substr($subject, 0, $length);

            if (array_key_exists($prefix, $this->namespaceMap)) {
                return $this->namespaceMap[$prefix];
            }
        }

        return null;
    }

    /**
     * Return the value whose namespace key is the longest prefix of $subject,
     * or null when nothing matches.
     *
     * One-shot convenience for callers that match a single subject and throw the map
     * away; anything in a loop wants `for()` instead.
     *
     * @template TValue2
     *
     * @param  array<string, TValue2>  $namespaceMap  namespace (trailing `\`) => value
     * @return TValue2|null
     */
    public static function longestPrefix(string $subject, array $namespaceMap)
    {
        return self::for($namespaceMap)->match($subject);
    }
}
