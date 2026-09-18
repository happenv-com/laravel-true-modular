<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistryCache;
use Illuminate\Console\Command;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

/**
 * Writes the module scan to `bootstrap/cache`, so booting reads one file instead of every
 * module's composer.json. Runs as part of `php artisan optimize`.
 *
 * Unlike Laravel's own caches this one never has to be cleared by hand after a module
 * changes: a cache that no longer matches the modules on disk is ignored (see
 * {@see ModuleRegistryCache}). A stale one only costs the scan it was meant to save.
 */
final class CacheModulesCommand extends Command
{
    protected $signature = 'true-modular:cache';

    protected $description = 'Cache the module registry so booting does not scan every module';

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function handle(): int
    {
        $modules = ModuleRegistryCache::make()->rebuild(base_path(Application::getModulesDirectory()));

        $this->components->info(sprintf('Module registry cached successfully (%d modules).', count($modules)));

        return self::SUCCESS;
    }
}
