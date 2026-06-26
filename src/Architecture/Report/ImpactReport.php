<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Report;

final readonly class ImpactReport implements ArchitectureReport
{
    /**
     * @param  array<string>  $direct
     * @param  array<string>  $indirect
     */
    public function __construct(
        public string $module,
        public array $direct,
        public array $indirect,
    ) {}

    public function total(): int
    {
        return count($this->direct) + count($this->indirect);
    }

    public function schemaName(): string
    {
        return 'impact';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'direct' => $this->direct,
            'indirect' => $this->indirect,
            'total' => $this->total(),
        ];
    }
}
