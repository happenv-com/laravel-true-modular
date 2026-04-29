<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular;

if (! function_exists('module_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param  string  $path
     */
    function module_path(string $module, $path = ''): string
    {
        return app()->basePath(sprintf('app-modules/%s/%s', $module, $path));
    }
}
