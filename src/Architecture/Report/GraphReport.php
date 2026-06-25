<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Report;

final readonly class GraphReport implements ArchitectureReport
{
    /**
     * @param  array<string>  $roots
     * @param  array<string, array<string>>  $dependents  node => sorted dependents
     */
    public function __construct(
        public array $roots,
        public array $dependents,
        public ?string $root,
    ) {}

    public function schemaName(): string
    {
        return 'graph';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function toArray(): array
    {
        return [
            'root' => $this->root,
            'roots' => $this->roots,
            'dependents' => $this->dependents,
        ];
    }
}
