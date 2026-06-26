<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

use function Safe\json_decode;
use function Safe\json_encode;

/**
 * Wire the converted module into the root composer.json: register a path
 * repository, require the module, and (on request) drop the stale App\ autoload.
 */
final class ConfigureComposer extends Step
{
    /**
     * Register {modulesDir}/* as a path repository (prepended, so it takes
     * priority over packagist) and require {vendor}/core. Leaves the existing
     * `autoload` untouched — Composer only warns about the now-missing app/ path.
     */
    public function wire(string $modulesDirectory, string $vendor): void
    {
        $path = $this->path('composer.json');

        if (! $this->files->exists($path)) {
            return;
        }

        /** @var array<string, mixed> $composer */
        $composer = json_decode($this->files->get($path), true);

        $repositories = is_array($composer['repositories'] ?? null) ? array_values($composer['repositories']) : [];
        $url = $modulesDirectory.'/*';
        if (! $this->hasPathRepository($repositories, $url)) {
            array_unshift($repositories, ['type' => 'path', 'url' => $url]);
        }
        $composer['repositories'] = $repositories;

        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $require[$vendor.'/core'] = '*';
        $composer['require'] = $require;

        $this->write($path, $composer);
    }

    /**
     * Remove the now-unused `"App\\": "app/"` PSR-4 autoload entry. Offered to
     * the user rather than done automatically.
     *
     * @return bool whether an entry was removed
     */
    public function removeAppAutoload(): bool
    {
        $path = $this->path('composer.json');

        if (! $this->files->exists($path)) {
            return false;
        }

        /** @var array<string, mixed> $composer */
        $composer = json_decode($this->files->get($path), true);

        $autoload = is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];
        $psr4 = is_array($autoload['psr-4'] ?? null) ? $autoload['psr-4'] : [];

        if (! array_key_exists('App\\', $psr4)) {
            return false;
        }

        unset($psr4['App\\']);
        $autoload['psr-4'] = $psr4;
        $composer['autoload'] = $autoload;

        $this->write($path, $composer);

        return true;
    }

    /** @param  list<mixed>  $repositories */
    private function hasPathRepository(array $repositories, string $url): bool
    {
        foreach ($repositories as $repository) {
            if (is_array($repository) && ($repository['type'] ?? null) === 'path' && ($repository['url'] ?? null) === $url) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $composer */
    private function write(string $path, array $composer): void
    {
        $this->files->put(
            $path,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }
}
