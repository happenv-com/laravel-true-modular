<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModularModuleProvider\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;

/**
 * @mixin ModuleProvider
 */
trait ProcessSchedules
{
    protected function processSchedules(): self
    {
        if (blank($this->module->scheduleFileNames)) {
            return $this;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            foreach ($this->module->scheduleFileNames as $scheduleFileName) {
                require_once $this->module->basePath(sprintf('/../routes/%s.php', $scheduleFileName));
            }
        });

        return $this;
    }
}
