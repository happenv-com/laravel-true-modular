<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Report;

final readonly class ModulesReport implements ArchitectureReport
{
    /**
     * @param  list<array{name: string, dependencies: array<string>, path: string, enabled: bool}>  $modules  in resolution order
     */
    public function __construct(
        public array $modules,
        public bool $reverse,
    ) {}

    public function schemaName(): string
    {
        return 'modules';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function toArray(): array
    {
        return [
            'order' => $this->reverse ? 'reverse' : 'topological',
            'modules' => $this->modules,
        ];
    }
}
