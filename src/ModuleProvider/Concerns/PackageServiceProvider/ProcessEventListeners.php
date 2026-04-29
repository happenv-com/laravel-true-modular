<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * @mixin ModuleProvider
 */
trait ProcessEventListeners
{
    /**
     * @throws RuntimeException
     */
    protected function processEventListeners(): self
    {
        if (blank($this->module->eventListeners)) {
            return $this;
        }

        foreach ($this->module->eventListeners as $eventListener) {
            $events = $eventListener['events'];
            $listener = $eventListener['listener'];

            Event::listen($events, $listener);

        }

        return $this;
    }
}
