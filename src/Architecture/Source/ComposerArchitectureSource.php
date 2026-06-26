<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

final readonly class ComposerArchitectureSource implements ArchitectureSource
{
    public function __construct(
        private ModuleRegistry $moduleRegistry,
        private ModuleLocator $locator,
    ) {}

    public function contribute(): iterable
    {
        yield new ModulesContribution($this->locator->all());

        yield new DependenciesContribution($this->moduleRegistry->getDependencyGraph());
    }
}
