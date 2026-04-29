<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModularModuleProvider\ModuleProvider;
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
