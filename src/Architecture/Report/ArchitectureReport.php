<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Report;

interface ArchitectureReport
{
    public function schemaName(): string;

    public function schemaVersion(): int;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
