<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;

use function Safe\json_encode;

final class JsonRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'json';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return true;
    }

    public function render(ArchitectureReport $report): string
    {
        $payload = [
            'schema' => [
                'name' => $report->schemaName(),
                'version' => $report->schemaVersion(),
            ],
            ...$report->toArray(),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
