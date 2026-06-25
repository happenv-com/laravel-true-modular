<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;

final class ComposerArchitectureSource implements ArchitectureSource
{
    public function __construct(
        private readonly ModuleTree $moduleTree,
        private readonly ModuleLocator $locator,
    ) {}

    public function contribute(): iterable
    {
        yield new ModulesContribution($this->locator->all());

        yield new DependenciesContribution($this->moduleTree->getDependencyGraph());
    }
}
