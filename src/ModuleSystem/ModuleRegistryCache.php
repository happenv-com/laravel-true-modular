<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Happenv\LaravelTrueModular\Application;
use Illuminate\Filesystem\Filesystem;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

use function Safe\filemtime;
use function Safe\filesize;
use function Safe\glob;

/**
 * The module scan, written to disk by `true-modular:cache` — which `php artisan optimize`
 * runs — and read back by {@see ModuleRegistry::make()} instead of scanning again.
 *
 * Every boot needs the module list: the provider sorter orders every provider by it. Without
 * this cache, producing it means reading and decoding every module's composer.json — measured
 * on a host with ~110 modules, about 2 ms of every boot, which a test suite pays once per test.
 *
 * VALIDATED, not frozen. Laravel's own caches (`config:cache`, `packages.php`) are trusted
 * until someone clears them, and a stale one fails with an error that names the wrong cause.
 * This one records a fingerprint of the composer.json files it was built from — which exist,
 * and each one's mtime and size — and is ignored as soon as that stops matching the disk: a
 * module added, removed or edited since the cache was written costs one full scan, never a
 * boot with the old module list. Checking the fingerprint is one glob and one stat per module,
 * roughly an eighth of the scan it replaces.
 *
 * @phpstan-type Modules array<string, array{name: string, path: string, composer: array<string, mixed>}>
 */
final readonly class ModuleRegistryCache
{
    /** Bump whenever the shape of the cached payload changes. */
    public const int FORMAT = 1;

    public function __construct(
        private string $path,
    ) {}

    /**
     * The running application's cache, next to Laravel's own in `bootstrap/cache`.
     */
    public static function make(): self
    {
        return new self(app()->bootstrapPath('cache/true-modular.php'));
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The modules in the given directory as last scanned — or null when there is no cache,
     * when it was written for another directory, module type or payload format, or when the
     * modules on disk have changed since.
     *
     * @return Modules|null
     */
    public function modules(string $appModulesPath): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        $cached = require $this->path;

        if (! is_array($cached)
            || ($cached['format'] ?? null) !== self::FORMAT
            || ($cached['modules_path'] ?? null) !== $appModulesPath
            || ($cached['composer_type'] ?? null) !== Application::getModuleComposerType()
            || ! is_array($cached['modules'] ?? null)) {
            return null;
        }

        try {
            if (($cached['fingerprint'] ?? null) !== $this->fingerprint($appModulesPath)) {
                return null;
            }
        } catch (FilesystemException) {
            // A manifest vanished between the glob and its stat: the modules are changing
            // right now, and the scan is the only answer that can be trusted.
            return null;
        }

        /** @var Modules $modules */
        $modules = $cached['modules'];

        return $modules;
    }

    /**
     * Scan the given directory afresh and write the result.
     *
     * The fingerprint is taken BEFORE the scan. The other way round, a composer.json edited
     * between the two would be cached with its old contents under its new fingerprint, and
     * trusted until the next edit; this way round the cache merely describes an older disk,
     * which the next read notices.
     *
     * @return Modules the modules written
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function rebuild(string $appModulesPath): array
    {
        $fingerprint = $this->fingerprint($appModulesPath);
        $modules = (new ModuleRegistry($appModulesPath))->getAllModules();

        $payload = [
            'format' => self::FORMAT,
            'modules_path' => $appModulesPath,
            'composer_type' => Application::getModuleComposerType(),
            'fingerprint' => $fingerprint,
            'modules' => $modules,
        ];

        $files = new Filesystem;
        $files->ensureDirectoryExists(dirname($this->path));

        // replace() writes a temporary file and renames it over the cache, so a boot racing
        // this command reads either the old cache or the new one, never half of one.
        $files->replace($this->path, '<?php return '.var_export($payload, return: true).';'.PHP_EOL);

        return $modules;
    }

    public function clear(): void
    {
        (new Filesystem)->delete($this->path);
    }

    /**
     * Which module manifests exist, and each one's mtime and size.
     *
     * @return array<string, string>
     *
     * @throws FilesystemException
     */
    private function fingerprint(string $appModulesPath): array
    {
        $fingerprint = [];

        foreach (glob($appModulesPath.'/*/composer.json') as $composerPath) {
            // filesize() is answered from PHP's stat cache: one stat per module, not two.
            $fingerprint[$composerPath] = filemtime($composerPath).':'.filesize($composerPath);
        }

        return $fingerprint;
    }
}
