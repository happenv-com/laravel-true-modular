<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular;

if (! function_exists('module_path')) {
    /**
     * Get the path to a file within a module's directory.
     */
    function module_path(string $module, string $path = ''): string
    {
        return app()->basePath(sprintf('%s/%s/%s', Application::getModulesDirectory(), $module, $path));
    }
}
