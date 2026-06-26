<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;

/**
 * @mixin ModuleProvider
 */
trait ProcessSchedules
{
    protected function processSchedules(): static
    {
        if (blank($this->module->scheduleFileNames)) {
            return $this;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            foreach ($this->module->scheduleFileNames as $scheduleFileName) {
                require_once $this->module->vendorPath('routes/'.$scheduleFileName.'.php');
            }
        });

        return $this;
    }
}
