<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExtensionAttributeResolver
{
    /** @param class-string $extension */
    public function __construct(private readonly string $extension) {}

    public function resolve(Model $model, string $key, mixed $notFound): mixed
    {
        $methodName = $this->getMethodName($key);

        $instance = new $this->extension($model);

        if (method_exists($instance, $methodName)) {
            return $instance->{$methodName}();
        }

        return $notFound;
    }

    public function getMethodName(string $key): string
    {
        return 'get' . Str::studly($key) . 'Attribute';
    }
}
