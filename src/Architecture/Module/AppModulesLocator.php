<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Module;

use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

final class AppModulesLocator implements ModuleLocator
{
    /** @var array<string, ModuleDescriptor>|null */
    private ?array $descriptors = null;

    public function __construct(
        private readonly ModuleTree $moduleTree,
        private readonly string $coreName = 'core',
    ) {}

    public function byClass(string $class): ?ModuleDescriptor
    {
        $class = ltrim($class, '\\');
        $best = null;
        $bestLength = -1;

        foreach ($this->all() as $descriptor) {
            $namespace = $descriptor->namespace;

            if ($namespace === null) {
                continue;
            }

            if (str_starts_with($class, $namespace) && strlen($namespace) > $bestLength) {
                $best = $descriptor;
                $bestLength = strlen($namespace);
            }
        }

        return $best;
    }

    public function byPath(string $path): ?ModuleDescriptor
    {
        $path = $this->normalize($path);
        $best = null;
        $bestLength = -1;

        foreach ($this->all() as $descriptor) {
            $base = $this->normalize($descriptor->path);

            if (str_starts_with($path, $base.'/') && strlen($base) > $bestLength) {
                $best = $descriptor;
                $bestLength = strlen($base);
            }
        }

        return $best;
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

        foreach ($this->moduleTree->getAllModules() as $name => $data) {
            $composer = $data['composer'];
            $shortName = $this->shortName($name);

            $psr4 = $composer['autoload']['psr-4'] ?? [];
            $namespace = $psr4 === [] ? null : $this->normalizeNamespace((string) array_key_first($psr4));

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

    private function normalizeNamespace(string $namespace): string
    {
        return rtrim($namespace, '\\').'\\';
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
