<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Illuminate\Events\QueuedClosure;
use InvalidArgumentException;

/**
 * @mixin Module
 */
trait HasEventListeners
{
    /**
     * The event listeners provided by the package.
     *
     * @var array<array{events: QueuedClosure|callable|string|array<QueuedClosure|callable|string>, listener: QueuedClosure|callable|array<class-string,string>|class-string}>
     */
    public array $eventListeners = [];

    /**
     * @param  QueuedClosure|callable|class-string|string|array<QueuedClosure|callable|class-string>  $events
     * @param  QueuedClosure|callable|class-string|array<QueuedClosure|callable|string,null|string>  $listener
     *
     * @throws InvalidArgumentException
     */
    public function hasEventListener(QueuedClosure | callable | string | array $events, QueuedClosure | callable | array | string $listener): static
    {
        $this->eventListeners[] = [
            'events' => $events,
            'listener' => $listener,
        ];

        return $this;
    }
}
