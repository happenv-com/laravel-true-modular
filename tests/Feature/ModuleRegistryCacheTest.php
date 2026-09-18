<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistryCache;
use Illuminate\Filesystem\Filesystem;

/**
 * A disposable copy of the fixture modules, for tests that add, edit or remove one.
 */
function copyOfFixtureModules(): string
{
    $directory = sys_get_temp_dir().'/true-modular-cache-'.bin2hex(random_bytes(6));

    (new Filesystem)->copyDirectory(appModulesFixture(), $directory);

    return $directory;
}

/**
 * Rewrite a cache file through a callback — how a test proves an answer came from the cache
 * and not from a scan: the scan can never produce what only the edited cache says.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>  $edit
 */
function editCacheFile(ModuleRegistryCache $cache, callable $edit): void
{
    $payload = require $cache->path();

    file_put_contents($cache->path(), '<?php return '.var_export($edit($payload), true).';');

    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($cache->path(), true);
    }
}

describe('ModuleRegistryCache', function (): void {
    it('answers nothing when no cache was written', function (): void {
        $modules = copyOfFixtureModules();

        expect((new ModuleRegistryCache($modules.'.php'))->modules($modules))->toBeNull();
    });

    it('answers exactly what a scan of the same modules answers', function (): void {
        $modules = copyOfFixtureModules();
        $cache = new ModuleRegistryCache($modules.'.php');

        $written = $cache->rebuild($modules);

        expect($cache->modules($modules))
            ->toBe($written)
            ->toBe((new ModuleRegistry($modules))->getAllModules());
    });

    it('is ignored once a module manifest is edited', function (): void {
        $modules = copyOfFixtureModules();
        $cache = new ModuleRegistryCache($modules.'.php');
        $cache->rebuild($modules);

        file_put_contents($modules.'/core/composer.json', PHP_EOL, FILE_APPEND);
        clearstatcache();

        expect($cache->modules($modules))->toBeNull();
    });

    it('is ignored once a module manifest is touched, even with its size unchanged', function (): void {
        $modules = copyOfFixtureModules();
        $cache = new ModuleRegistryCache($modules.'.php');
        $cache->rebuild($modules);

        touch($modules.'/core/composer.json', time() + 60);
        clearstatcache();

        expect($cache->modules($modules))->toBeNull();
    });

    it('is ignored once a module is added', function (): void {
        $modules = copyOfFixtureModules();
        $cache = new ModuleRegistryCache($modules.'.php');
        $cache->rebuild($modules);

        (new Filesystem)->copyDirectory($modules.'/kernel', $modules.'/returns');

        expect($cache->modules($modules))->toBeNull();
    });

    it('is ignored once a module is removed', function (): void {
        $modules = copyOfFixtureModules();
        $cache = new ModuleRegistryCache($modules.'.php');
        $cache->rebuild($modules);

        (new Filesystem)->deleteDirectory($modules.'/amazon');

        expect($cache->modules($modules))->toBeNull();
    });

    it('is ignored for any other modules directory', function (): void {
        $modules = copyOfFixtureModules();
        $cache = new ModuleRegistryCache($modules.'.php');
        $cache->rebuild($modules);

        expect($cache->modules(copyOfFixtureModules()))->toBeNull();
    });

    it('is ignored once the application looks for another module type', function (): void {
        $modules = copyOfFixtureModules();
        $cache = new ModuleRegistryCache($modules.'.php');
        $cache->rebuild($modules);

        $type = Application::getModuleComposerType();
        Application::moduleComposerType('another-module-type');

        try {
            expect($cache->modules($modules))->toBeNull();
        } finally {
            Application::moduleComposerType($type);
        }
    });

    it('is ignored when it was written in another payload format', function (): void {
        $modules = copyOfFixtureModules();
        $cache = new ModuleRegistryCache($modules.'.php');
        $cache->rebuild($modules);

        editCacheFile($cache, static fn (array $payload): array => [...$payload, 'format' => ModuleRegistryCache::FORMAT - 1]);

        expect($cache->modules($modules))->toBeNull();
    });

    it('removes its file, and removing it again is not an error', function (): void {
        $modules = copyOfFixtureModules();
        $cache = new ModuleRegistryCache($modules.'.php');
        $cache->rebuild($modules);

        $cache->clear();
        $cache->clear();

        expect($cache->path())->not->toBeFile();
    });
});

describe('ModuleRegistry::make()', function (): void {
    afterEach(function (): void {
        ModuleRegistryCache::make()->clear();
    });

    it('answers from the cache while it still matches the modules', function (): void {
        $cache = ModuleRegistryCache::make();
        $cache->rebuild(base_path(Application::getModulesDirectory()));

        editCacheFile($cache, static function (array $payload): array {
            $payload['modules']['myapp/only-in-the-cache'] = [
                'composer' => ['name' => 'myapp/only-in-the-cache'],
                'name' => 'myapp/only-in-the-cache',
                'path' => '/nowhere',
            ];

            return $payload;
        });

        expect(ModuleRegistry::make()->getModuleNames())->toContain('myapp/only-in-the-cache');
    });

    it('scans when the cache no longer matches the modules', function (): void {
        $cache = ModuleRegistryCache::make();
        $cache->rebuild(base_path(Application::getModulesDirectory()));

        editCacheFile($cache, static function (array $payload): array {
            $payload['modules']['myapp/only-in-the-cache'] = [
                'composer' => ['name' => 'myapp/only-in-the-cache'],
                'name' => 'myapp/only-in-the-cache',
                'path' => '/nowhere',
            ];
            $payload['fingerprint'] = array_map(static fn (): string => '0:0', $payload['fingerprint']);

            return $payload;
        });

        expect(ModuleRegistry::make()->getModuleNames())
            ->not->toContain('myapp/only-in-the-cache')
            ->toContain('myapp/core');
    });

    it('scans when there is no cache', function (): void {
        expect(ModuleRegistry::make()->getModuleNames())->toContain('myapp/core', 'myapp/sale');
    });
});

it('rediscovers from disk after clearCache(), even when it started from a cached scan', function (): void {
    $registry = new ModuleRegistry(appModulesFixture(), [
        'myapp/only-in-the-cache' => ['composer' => [], 'name' => 'myapp/only-in-the-cache', 'path' => '/nowhere'],
    ]);

    expect($registry->getModuleNames())->toBe(['myapp/only-in-the-cache']);

    $registry->clearCache();

    expect($registry->getModuleNames())->toContain('myapp/core')->not->toContain('myapp/only-in-the-cache');
});
