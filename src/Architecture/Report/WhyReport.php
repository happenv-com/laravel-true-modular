<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Report;

final readonly class WhyReport implements ArchitectureReport
{
    /**
     * @param  array<string>|null  $path
     */
    public function __construct(
        public string $from,
        public string $to,
        public ?array $path,
    ) {}

    public function schemaName(): string
    {
        return 'why';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'path' => $this->path,
        ];
    }
}
