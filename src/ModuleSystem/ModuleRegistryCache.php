<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
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
 * Next to the scan it keeps what every boot derives from it: the modules' topological order,
 * which orders the service providers, and a signature of both, under which the provider sorter
 * keeps the order it arrived at for the rest of the process. Deriving them at boot was ~0.4 ms
 * and ~0.8 ms of every boot on a host with 115 modules and 286 providers.
 *
 * @phpstan-type Modules array<string, array{name: string, path: string, composer: array<string, mixed>}>
 * @phpstan-type Snapshot array{modules: Modules, topological_order: array<string>|null, signature: string}
 */
final readonly class ModuleRegistryCache
{
    /** Bump whenever the shape of the cached payload changes. */
    public const int FORMAT = 2;

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
        return $this->load($appModulesPath)['modules'] ?? null;
    }

    /**
     * {@see modules()} together with what was derived from them when the cache was written: their
     * topological order and a signature of both. Null in every case in which `modules()` is.
     *
     * The order is null for modules with a circular dependency. The exception belongs to the boot
     * that orders providers by it, as it always has — not to `true-modular:cache`, which would
     * otherwise turn `php artisan optimize` into the place that reports a dependency cycle.
     *
     * @return Snapshot|null
     */
    public function load(string $appModulesPath): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        $cached = require $this->path;

        if (! is_array($cached)
            || ($cached['format'] ?? null) !== self::FORMAT
            || ($cached['modules_path'] ?? null) !== $appModulesPath
            || ($cached['composer_type'] ?? null) !== Application::getModuleComposerType()
            || ! is_array($cached['modules'] ?? null)
            || ! is_array($cached['topological_order'] ?? null) && ($cached['topological_order'] ?? null) !== null
            || ! is_string($cached['signature'] ?? null)) {
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

        /** @var Snapshot $snapshot */
        $snapshot = [
            'modules' => $cached['modules'],
            'topological_order' => $cached['topological_order'],
            'signature' => $cached['signature'],
        ];

        return $snapshot;
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
        $registry = new ModuleRegistry($appModulesPath);
        $modules = $registry->getAllModules();

        try {
            $topologicalOrder = $registry->getTopologicalOrder();
        } catch (CircularDependencyException) {
            $topologicalOrder = null;
        }

        $payload = [
            'format' => self::FORMAT,
            'modules_path' => $appModulesPath,
            'composer_type' => Application::getModuleComposerType(),
            'fingerprint' => $fingerprint,
            'modules' => $modules,
            'topological_order' => $topologicalOrder,
            'signature' => hash('xxh128', serialize([$modules, $topologicalOrder])),
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
