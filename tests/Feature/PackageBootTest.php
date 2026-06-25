<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;

it('boots the package and resolves ModuleTree against fixtures', function (): void {
    $tree = app(ModuleTree::class);

    expect($tree)->toBeInstanceOf(ModuleTree::class)
        ->and($tree->getModuleNames())->toContain('myapp/core', 'myapp/amazon');
});
