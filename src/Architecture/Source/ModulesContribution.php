<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

final readonly class ModulesContribution implements ArchitectureContribution
{
    /**
     * @param  array<string, ModuleDescriptor>  $modules
     */
    public function __construct(public array $modules) {}

    public function applyTo(MutableArchitectureIndex $index): void
    {
        $index->addModules($this->modules);
    }
}
