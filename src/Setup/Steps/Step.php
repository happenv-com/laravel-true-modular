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

    /**
     * Path relative to the application base, normalized to forward slashes with
     * the leading separator stripped — so results are stable on Windows, where
     * SplFileInfo paths use backslashes.
     */
    protected function relative(string $absolute): string
    {
        $base = str_replace('\\', '/', $this->basePath);
        $path = str_replace('\\', '/', $absolute);

        return ltrim(str_replace($base, '', $path), '/');
    }
}
