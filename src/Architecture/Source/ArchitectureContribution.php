<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

interface ArchitectureContribution
{
    /**
     * Apply this contribution to the index being assembled. New contribution
     * kinds plug in here without the builder needing to know about them.
     */
    public function applyTo(MutableArchitectureIndex $index): void;
}
