<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

/**
 * Per-invocation rendering options. Owns the display rule: default-vendor
 * modules show their short name; external modules (and everything under
 * --with-vendor) show their full vendor/name.
 */
final readonly class RenderContext
{
    public function __construct(
        public string $defaultVendor,
        public bool $withVendor = false,
    ) {}

    public function display(string $fullName): string
    {
        if ($this->withVendor) {
            return $fullName;
        }

        $firstSlash = strpos($fullName, '/');

        if ($firstSlash === false) {
            return $fullName;
        }

        if (substr($fullName, 0, $firstSlash) === $this->defaultVendor) {
            $lastSlash = (int) strrpos($fullName, '/');

            return substr($fullName, $lastSlash + 1);
        }

        return $fullName;
    }
}
