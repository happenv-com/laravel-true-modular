<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Config;

/**
 * Recursive config merge with three deliberate, value-type-dependent strategies:
 *
 *  - **Lists** (sequential arrays): unioned and de-duplicated
 *    (`array_unique(..., SORT_REGULAR)`) regardless of $overwrite — module and
 *    host list entries are always combined, never replaced.
 *  - **Associative arrays**: merged recursively, carrying $overwrite down.
 *  - **Scalars / type mismatches**: the host value wins unless $overwrite is
 *    true, in which case the extending value replaces it.
 *
 * Keys are `ksort`ed at every level, so the merged result is ordered
 * deterministically by key rather than by insertion order.
 */
final class ConfigMerger implements ConfigMergerContract
{
    /**
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $extending
     * @return array<array-key, mixed>
     */
    public function merge(array $original, array $extending, bool $overwrite = false): array
    {
        foreach ($extending as $key => $value) {
            if (! \array_key_exists($key, $original)) {
                $original[$key] = $value;

                continue;
            }

            $originalValue = $original[$key];

            if (\is_array($value) && \is_array($originalValue)) {
                if (\array_is_list($value) && \array_is_list($originalValue)) {

                    $original[$key] = \array_values(\array_unique(\array_merge($originalValue, $value), SORT_REGULAR));
                } else {
                    $original[$key] = $this->merge($originalValue, $value, $overwrite);
                }

                continue;
            }

            if ($overwrite) {
                $original[$key] = $value;
            }
        }

        \ksort($original);

        return $original;
    }
}
