<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

final readonly class DependenciesContribution implements ArchitectureContribution
{
    /**
     * @param  array<string, array<string>>  $edges  node => direct dependencies
     */
    public function __construct(public array $edges) {}
}
