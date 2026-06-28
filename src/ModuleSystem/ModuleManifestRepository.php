<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Happenv\LaravelTrueModular\ModuleProvider\Module;

final class ModuleManifestRepository
{
    /**
     * @var array<string, Module>
     */
    private array $modules = [];

    public function register(Module $module): void
    {
        $this->modules[$module->shortName()] = $module;
    }

    /**
     * @return array<string, Module>
     */
    public function all(): array
    {
        return $this->modules;
    }

    public function find(string $name): ?Module
    {
        return $this->modules[$name] ?? null;
    }
}
