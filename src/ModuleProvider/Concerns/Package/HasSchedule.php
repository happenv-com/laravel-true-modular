<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\Support\MergesFlattened;
use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasSchedule
{
    use MergesFlattened;

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
        $this->scheduleFileNames = $this->mergeFlattened($this->scheduleFileNames, $scheduleFileNames);

        return $this;
    }
}
