<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\Support\MergesFlattened;
use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasCommands
{
    use MergesFlattened;

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
        $this->commands = $this->mergeFlattened($this->commands, $commandClassNames);

        return $this;
    }

    public function hasConsoleCommand(string $commandClassName): static
    {
        $this->consoleCommands[] = $commandClassName;

        return $this;
    }

    public function hasConsoleCommands(string ...$commandClassNames): static
    {
        $this->consoleCommands = $this->mergeFlattened($this->consoleCommands, $commandClassNames);

        return $this;
    }
}
