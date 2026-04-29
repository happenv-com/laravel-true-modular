<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Closure;

trait InitializeCallbacks
{
    /**
     * All of the registered initializing callbacks.
     *
     * @var array<mixed>
     */
    protected $initializingCallbacks = [];

    /**
     * All of the registered initialized callbacks.
     *
     * @var array<mixed>
     */
    protected $initializedCallbacks = [];

    /**
     * Register an initializing callback to be run before the "initialize" method is called.
     */
    public function initializing(Closure $callback): void
    {
        $this->initializingCallbacks[] = $callback;
    }

    /**
     * Register an initialized callback to be run after the "initialize" method is called.
     */
    public function initialized(Closure $callback): void
    {
        $this->initializedCallbacks[] = $callback;
    }

    /**
     * Call the registered initializing callbacks.
     */
    public function callInitializingCallbacks(): void
    {
        $index = 0;

        while ($index < count($this->initializingCallbacks)) {
            $this->app->call($this->initializingCallbacks[$index]);

            $index++;
        }
    }

    /**
     * Call the registered initialized callbacks.
     */
    public function callInitializedCallbacks(): void
    {
        $index = 0;

        while ($index < count($this->initializedCallbacks)) {
            $this->app->call($this->initializedCallbacks[$index]);

            $index++;
        }
    }
}
