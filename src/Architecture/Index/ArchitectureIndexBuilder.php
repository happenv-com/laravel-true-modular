<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Index;

use Happenv\LaravelTrueModular\Architecture\Source\ArchitectureSource;

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
        $accumulator = new IndexAccumulator;

        foreach ($this->sources as $source) {
            foreach ($source->contribute() as $contribution) {
                $contribution->applyTo($accumulator);
            }
        }

        return $accumulator->build();
    }
}
