<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Index;

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Source\ArchitectureSource;
use Happenv\LaravelTrueModular\Architecture\Source\DependenciesContribution;
use Happenv\LaravelTrueModular\Architecture\Source\ModulesContribution;

final readonly class ArchitectureIndexBuilder
{
    /**
     * @param  iterable<ArchitectureSource>  $sources
     */
    public function __construct(
        private iterable $sources,
    ) {}

    public function build(): ArchitectureIndex
    {
        $modules = [];
        $edges = [];

        foreach ($this->sources as $source) {
            foreach ($source->contribute() as $contribution) {
                if ($contribution instanceof ModulesContribution) {
                    foreach ($contribution->modules as $name => $descriptor) {
                        $modules[$name] = $descriptor;
                    }

                    continue;
                }

                if ($contribution instanceof DependenciesContribution) {
                    foreach ($contribution->edges as $node => $deps) {
                        $edges[$node] = array_values(array_unique([
                            ...($edges[$node] ?? []),
                            ...$deps,
                        ]));
                    }
                }
            }
        }

        ksort($modules);
        ksort($edges);

        return new ArchitectureIndex($modules, new DependencyGraph($edges));
    }
}
