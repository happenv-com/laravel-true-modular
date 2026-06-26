<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

use Illuminate\Filesystem\Filesystem;

/**
 * Base for the discrete file mutations that make up `true-modular:setup`.
 *
 * Each step owns one concern and resolves its paths relative to the host
 * application's base path, so it can be unit-tested against a scaffold.
 */
abstract class Step
{
    public function __construct(
        protected readonly Filesystem $files,
        protected readonly string $basePath,
    ) {}

    protected function path(string $relative): string
    {
        return $this->basePath.'/'.ltrim($relative, '/');
    }

    /** Path relative to the application base, with leading separator stripped. */
    protected function relative(string $absolute): string
    {
        return ltrim(str_replace($this->basePath, '', $absolute), '/');
    }
}
