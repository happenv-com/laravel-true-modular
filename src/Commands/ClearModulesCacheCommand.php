<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistryCache;
use Illuminate\Console\Command;

/**
 * Removes the module registry cache written by `true-modular:cache`. Runs as part of
 * `php artisan optimize:clear`.
 */
final class ClearModulesCacheCommand extends Command
{
    protected $signature = 'true-modular:clear';

    protected $description = 'Remove the module registry cache file';

    public function handle(): int
    {
        ModuleRegistryCache::make()->clear();

        $this->components->info('Module registry cache cleared successfully.');

        return self::SUCCESS;
    }
}
