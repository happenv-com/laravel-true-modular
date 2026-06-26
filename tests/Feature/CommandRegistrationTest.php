<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('registers the package console commands via the service provider boot phase', function (): void {
    // No manual Artisan::registerCommand here — these must come from
    // KernelServiceProvider::boot(), which runs under any Application (not just
    // our custom one), so they are available on a fresh install before setup.
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain(
        'true-modular:setup',
        'module:make',
        'module:make:migration',
        'module:list',
        'module:seed',
        'module:graph',
        'module:impact',
        'module:why',
    );
});
