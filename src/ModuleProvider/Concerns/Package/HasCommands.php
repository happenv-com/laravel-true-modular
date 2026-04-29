<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasCommands
{
    /**
     * @var string[]
     */
    public array $commands = [];

    /**
     * @var string[]
     */
    public array $consoleCommands = [];

    public function hasCommand(string $commandClassName): static
    {
        $this->commands[] = $commandClassName;

        return $this;
    }

    public function hasCommands(string ...$commandClassNames): static
    {
        $this->commands = array_merge(
            $this->commands,
            collect($commandClassNames)->flatten()->toArray()
        );

        return $this;
    }

    public function hasConsoleCommand(string $commandClassName): static
    {
        $this->consoleCommands[] = $commandClassName;

        return $this;
    }

    public function hasConsoleCommands(string ...$commandClassNames): static
    {
        $this->consoleCommands = array_merge(
            $this->consoleCommands,
            collect($commandClassNames)->flatten()->toArray()
        );

        return $this;
    }
}
