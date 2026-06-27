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

        $slash = strpos($fullName, '/');

        if ($slash === false) {
            return $fullName;
        }

        if (substr($fullName, 0, $slash) === $this->defaultVendor) {
            return substr($fullName, $slash + 1);
        }

        return $fullName;
    }
}
