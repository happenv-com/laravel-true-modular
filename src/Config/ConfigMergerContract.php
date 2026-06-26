<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Config;

interface ConfigMergerContract
{
    /**
     * Recursively merge $extending into $original. See {@see ConfigMerger} for
     * the per-value-type strategy and the $overwrite contract.
     *
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $extending
     * @return array<array-key, mixed>
     */
    public function merge(array $original, array $extending, bool $overwrite = false): array;
}
