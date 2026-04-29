<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModularModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasSchedule
{
    /**
     * @var string[]
     */
    public array $scheduleFileNames = [];

    public function hasSchedule(string $scheduleFileName = 'schedule'): static
    {
        $this->scheduleFileNames[] = $scheduleFileName;

        return $this;
    }

    public function hasSchedules(string ...$scheduleFileNames): static
    {
        $this->scheduleFileNames = array_merge(
            $this->scheduleFileNames,
            collect($scheduleFileNames)->flatten()->toArray()
        );

        return $this;
    }
}
