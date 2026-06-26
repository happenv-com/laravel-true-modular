<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Index;

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;
use InvalidArgumentException;

final readonly class ArchitectureIndex
{
    /**
     * @param  array<string, ModuleDescriptor>  $modules
     * @param  DependencyGraph<string>  $graph
     */
    public function __construct(
        private array $modules,
        private DependencyGraph $graph,
    ) {}

    public function modules(): ModuleQuery
    {
        return (new ModuleQuery($this->modules, $this->graph))->sortedByName();
    }

    public function module(string $name): ?ModuleDescriptor
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * @return DependencyGraph<string>
     */
    public function graph(): DependencyGraph
    {
        return $this->graph;
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /**
     * @throws InvalidArgumentException when the module is not part of the index
     */
    public function assertKnown(string $name): void
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException(sprintf('Unknown module [%s].', $name));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $modules = [];
        $dependencies = [];

        foreach ($this->graph->nodes() as $name) {
            $dependencies[$name] = $this->graph->dependencies($name);
            $descriptor = $this->modules[$name] ?? null;
            $modules[$name] = $descriptor?->toArray();
        }

        return [
            'modules' => $modules,
            'dependencies' => $dependencies,
        ];
    }
}
