<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Support\Facades\Log;

/**
 * @mixin ModuleProvider
 */
trait GuardsModulePaths
{
    /**
     * Determine whether a directory required by an enabled module capability is
     * missing.
     *
     * When the directory is absent a warning is logged and `true` is returned so
     * the caller can skip the capability instead of throwing. This keeps a module
     * that declares a capability (e.g. `hasViews()`) but ships no matching
     * directory from crashing the whole application boot — most notably on a
     * fresh CI checkout, where empty directories are not tracked by git and are
     * therefore absent.
     */
    protected function moduleDirectoryMissing(string $path, string $capability): bool
    {
        if (is_dir($path)) {
            return false;
        }

        Log::warning(sprintf(
            'laravel-true-modular: module [%s] enables "%s" but its directory does not exist: %s. Skipping.',
            $this->module->name,
            $capability,
            $path,
        ));

        return true;
    }
}
