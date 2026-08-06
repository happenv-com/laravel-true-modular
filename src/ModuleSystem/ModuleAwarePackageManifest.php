<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Override;

/**
 * Removes disabled modules — and the vendor packages they own — from Laravel's
 * package discovery manifest.
 *
 * Filtering happens in getManifest() rather than in providers(), because a
 * package cut there loses its service providers AND its facade aliases in one
 * move; filtering providers() alone would leave a dangling alias behind.
 */
final class ModuleAwarePackageManifest extends PackageManifest
{
    /** @var array<string, mixed>|null */
    private ?array $filteredManifest = null;

    /**
     * @param  Closure(): ModuleActivation  $activationResolver  resolved lazily: the manifest is
     *                                                          built during RegisterProviders, before the package's own
     *                                                          service provider has bound anything.
     */
    public function __construct(
        Filesystem $files,
        string $basePath,
        string $manifestPath,
        private readonly Closure $activationResolver,
    ) {
        parent::__construct($files, $basePath, $manifestPath);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getManifest(): array
    {
        if ($this->filteredManifest !== null) {
            return $this->filteredManifest;
        }

        /** @var array<string, mixed> $manifest */
        $manifest = parent::getManifest();

        $excluded = ($this->activationResolver)()->excludedPackages();

        return $this->filteredManifest = $excluded === []
            ? $manifest
            : array_diff_key($manifest, array_flip($excluded));
    }
}
