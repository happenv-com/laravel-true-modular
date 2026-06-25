<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

interface ArchitectureSource
{
    /**
     * @return iterable<ArchitectureContribution>
     */
    public function contribute(): iterable;
}
