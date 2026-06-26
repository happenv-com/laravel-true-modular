<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessCommands
{
    protected function processCommands(): static
    {
        if (blank($this->module->commands)) {
            return $this;
        }

        $this->commands($this->module->commands);

        return $this;
    }

    protected function processConsoleCommands(): static
    {
        if (blank($this->module->consoleCommands) || ! $this->app->runningInConsole()) {
            return $this;
        }

        $this->commands($this->module->consoleCommands);

        return $this;
    }
}
