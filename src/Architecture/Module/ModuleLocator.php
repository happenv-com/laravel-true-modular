<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Module;

interface ModuleLocator
{
    public function byClass(string $class): ?ModuleDescriptor;

    public function byPath(string $path): ?ModuleDescriptor;

    public function byComposerPackage(string $package): ?ModuleDescriptor;

    /**
     * @return array<string, ModuleDescriptor> keyed by composer package
     *
     * @throws \Safe\Exceptions\FilesystemException
     * @throws \Safe\Exceptions\JsonException
     */
    public function all(): array;
}
