<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

/**
 * @mixin ModuleProvider
 */
trait ProcessCommands
{
    protected function processCommands(): self
    {
        if (blank($this->module->commands)) {
            return $this;
        }

        $this->commands($this->module->commands);

        return $this;
    }

    protected function processConsoleCommands(): self
    {
        if (blank($this->module->consoleCommands) || ! $this->app->runningInConsole()) {
            return $this;
        }

        $this->commands($this->module->consoleCommands);

        return $this;
    }
}
