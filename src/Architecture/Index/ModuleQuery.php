<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Index;

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

/**
 * Disciplined, immutable architectural query over modules.
 * Deliberately NOT a generic collection — only architectural filters.
 */
final readonly class ModuleQuery
{
    /**
     * @param  array<string, ModuleDescriptor>  $modules
     * @param  DependencyGraph<string>  $graph
     */
    public function __construct(
        private array $modules,
        private DependencyGraph $graph,
    ) {}

    public function dependingOn(string $module): self
    {
        $dependents = $this->graph->dependents($module);

        return $this->only($dependents);
    }

    public function sortedByName(): self
    {
        $modules = $this->modules;
        ksort($modules);

        return new self($modules, $this->graph);
    }

    public function sortedByDepth(): self
    {
        $modules = $this->modules;

        uasort($modules, function (ModuleDescriptor $a, ModuleDescriptor $b): int {
            $depth = $this->graph->dependencyDepth($a->name) <=> $this->graph->dependencyDepth($b->name);

            return $depth !== 0 ? $depth : ($a->name <=> $b->name);
        });

        return new self($modules, $this->graph);
    }

    /** @return array<string> */
    public function names(): array
    {
        return array_keys($this->modules);
    }

    /**
     * @param  array<string>  $names
     */
    private function only(array $names): self
    {
        $filtered = [];

        foreach ($names as $name) {
            if (isset($this->modules[$name])) {
                $filtered[$name] = $this->modules[$name];
            }
        }

        ksort($filtered);

        return new self($filtered, $this->graph);
    }
}
