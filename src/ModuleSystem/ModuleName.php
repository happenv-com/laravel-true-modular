<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Illuminate\Support\Str;

/**
 * Pure module-name helpers: derive the default vendor from a namespace and
 * qualify a typed name. The single home for the "no slash → prepend vendor"
 * rule and Composer-style lowercasing.
 */
final class ModuleName
{
    public static function vendorFromNamespace(string $namespace): string
    {
        return Str::kebab(class_basename(str_replace('\\', '/', $namespace)));
    }

    public static function qualify(string $name, string $vendor): string
    {
        $name = Str::lower($name);

        return str_contains($name, '/') ? $name : $vendor.'/'.$name;
    }
}
