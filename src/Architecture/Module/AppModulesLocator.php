<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Module;

use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Happenv\LaravelTrueModular\ModuleSystem\NamespaceMatcher;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

final class AppModulesLocator implements ModuleLocator
{
    /** @var array<string, ModuleDescriptor>|null */
    private ?array $descriptors = null;

    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly string $coreName = 'core',
    ) {}

    public function byClass(string $class): ?ModuleDescriptor
    {
        $namespaceMap = [];

        foreach ($this->all() as $descriptor) {
            if ($descriptor->namespace !== null) {
                $namespaceMap[$descriptor->namespace] = $descriptor;
            }
        }

        return NamespaceMatcher::longestPrefix(ltrim($class, '\\'), $namespaceMap);
    }

    public function byPath(string $path): ?ModuleDescriptor
    {
        // Key each descriptor by its base path plus a trailing separator so the
        // longest-prefix match requires a real directory boundary (a file inside
        // the module), reusing the shared matcher rather than re-rolling the loop.
        $pathMap = [];

        foreach ($this->all() as $descriptor) {
            $pathMap[$this->normalize($descriptor->path).'/'] = $descriptor;
        }

        return NamespaceMatcher::longestPrefix($this->normalize($path), $pathMap);
    }

    public function byComposerPackage(string $package): ?ModuleDescriptor
    {
        return $this->all()[$package] ?? null;
    }

    /**
     * @return array<string, ModuleDescriptor>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function all(): array
    {
        if ($this->descriptors !== null) {
            return $this->descriptors;
        }

        $descriptors = [];

        foreach ($this->moduleRegistry->getAllModules() as $name => $data) {
            $composer = $data['composer'];
            $shortName = $this->shortName($name);

            $namespace = $this->moduleRegistry->getModuleNamespace($name);

            $providers = $composer['extra']['laravel']['providers'] ?? [];

            $descriptors[$name] = new ModuleDescriptor(
                name: $name,
                shortName: $shortName,
                path: $data['path'],
                namespace: $namespace,
                version: isset($composer['version']) ? (string) $composer['version'] : null,
                provider: $providers === [] ? null : (string) $providers[0],
                require: $this->stringMap($composer['require'] ?? []),
                isCore: $shortName === $this->coreName,
            );
        }

        ksort($descriptors);

        return $this->descriptors = $descriptors;
    }

    private function shortName(string $package): string
    {
        $position = strrpos($package, '/');

        return $position === false ? $package : substr($package, $position + 1);
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function stringMap(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[(string) $key] = (string) $value;
        }

        return $result;
    }
}
