<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\Support;

/**
 * Shared accumulator for the `hasXs(string ...$values)` builder methods: appends
 * a flattened set of values to an existing list, so callers may pass either
 * spread arguments or a single array.
 */
trait MergesFlattened
{
    /**
     * @param  array<mixed>  $current
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    protected function mergeFlattened(array $current, array $values): array
    {
        return array_merge(
            $current,
            collect($values)->flatten()->toArray()
        );
    }
}
