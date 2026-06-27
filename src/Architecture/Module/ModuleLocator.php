<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Module;

use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

interface ModuleLocator
{
    public function byClass(string $class): ?ModuleDescriptor;

    public function byPath(string $path): ?ModuleDescriptor;

    public function byComposerPackage(string $package): ?ModuleDescriptor;

    public function resolve(string $name): ?ModuleDescriptor;

    public function resolveOrFail(string $name): ModuleDescriptor;

    /**
     * @return array<string, ModuleDescriptor> keyed by composer package
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function all(): array;
}
