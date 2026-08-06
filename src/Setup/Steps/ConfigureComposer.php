<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

use Happenv\LaravelTrueModular\Application;

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
     * priority over packagist) and require the given module package at the given
     * version. Leaves the existing `autoload` untouched — Composer only warns
     * about a missing path.
     */
    public function wire(string $modulesDirectory, string $package, string $version = Application::DEFAULT_MODULE_VERSION): void
    {
        $path = $this->path('composer.json');

        if (! $this->files->exists($path)) {
            return;
        }

        /** @var array<string, mixed> $composer */
        $composer = json_decode($this->files->get($path), associative: true);

        $repositories = is_array($composer['repositories'] ?? null) ? array_values($composer['repositories']) : [];
        $url = $modulesDirectory.'/*';
        if (! $this->hasPathRepository($repositories, $url)) {
            array_unshift($repositories, ['type' => 'path', 'url' => $url]);
        }
        $composer['repositories'] = $repositories;

        /** @var array<string, string> $require */
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $composer['require'] = $this->insertRequirement($require, $package, $version);

        $this->write($path, $composer);
    }

    /**
     * Place the requirement where Composer's own `sort-packages` would keep it,
     * instead of appending. Appending leaves the entry out of order in a sorted
     * file, so the next `composer normalize` (or any contributor with
     * `sort-packages` on) moves it and the scaffolding shows up as an unrelated
     * diff hunk.
     *
     * Existing entries are never reordered — this is an insertion, not a sort, so
     * a hand-ordered `require` block survives untouched. Platform requirements
     * (`php`, `ext-*`: no slash) are skipped over, since Composer keeps them first
     * regardless of alphabet.
     *
     * @param  array<string, string>  $require
     * @return array<string, string>
     */
    private function insertRequirement(array $require, string $package, string $version): array
    {
        if (array_key_exists($package, $require)) {
            $require[$package] = $version;

            return $require;
        }

        $result = [];
        $inserted = false;

        foreach ($require as $name => $constraint) {
            if (! $inserted && str_contains((string) $name, '/') && strcmp((string) $name, $package) > 0) {
                $result[$package] = $version;
                $inserted = true;
            }

            $result[$name] = $constraint;
        }

        if (! $inserted) {
            $result[$package] = $version;
        }

        return $result;
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
        $composer = json_decode($this->files->get($path), associative: true);

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
