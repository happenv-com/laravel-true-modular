<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

/**
 * Mutable accumulator a contribution writes itself into. Lets each contribution
 * apply itself ({@see ArchitectureContribution::applyTo()}) instead of the
 * builder switching on contribution types.
 */
interface MutableArchitectureIndex
{
    /**
     * @param  array<string, ModuleDescriptor>  $modules
     */
    public function addModules(array $modules): void;

    /**
     * @param  array<string, array<string>>  $edges  node => direct dependencies
     */
    public function addDependencies(array $edges): void;
}
