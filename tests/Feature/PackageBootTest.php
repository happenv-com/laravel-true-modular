<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

it('boots the package and resolves ModuleRegistry against fixtures', function (): void {
    $tree = app(ModuleRegistry::class);

    expect($tree)->toBeInstanceOf(ModuleRegistry::class)
        ->and($tree->getModuleNames())->toContain('myapp/core', 'myapp/amazon');
});
