<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Index;

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;
use Happenv\LaravelTrueModular\Architecture\Source\MutableArchitectureIndex;

/**
 * Gathers module descriptors and dependency edges from contributions, then
 * freezes them into an immutable {@see ArchitectureIndex}.
 */
final class IndexAccumulator implements MutableArchitectureIndex
{
    /** @var array<string, ModuleDescriptor> */
    private array $modules = [];

    /** @var array<string, array<string>> */
    private array $edges = [];

    public function addModules(array $modules): void
    {
        foreach ($modules as $name => $descriptor) {
            $this->modules[$name] = $descriptor;
        }
    }

    public function addDependencies(array $edges): void
    {
        foreach ($edges as $node => $deps) {
            $this->edges[$node] = array_values(array_unique([
                ...($this->edges[$node] ?? []),
                ...$deps,
            ]));
        }
    }

    public function build(): ArchitectureIndex
    {
        ksort($this->modules);
        ksort($this->edges);

        return new ArchitectureIndex($this->modules, new DependencyGraph($this->edges));
    }
}
