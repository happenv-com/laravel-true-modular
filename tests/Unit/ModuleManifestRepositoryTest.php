<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;

it('stores and finds a module by its short name', function (): void {
    $repository = new ModuleManifestRepository;
    $billing = (new Module)->name('acme/laravel-billing');

    $repository->register($billing);

    expect($repository->find('billing'))->toBe($billing)
        ->and($repository->all())->toBe(['billing' => $billing])
        ->and($repository->find('missing'))->toBeNull();
});

it('keeps multiple registered modules', function (): void {
    $repository = new ModuleManifestRepository;
    $a = (new Module)->name('acme/laravel-a');
    $b = (new Module)->name('acme/laravel-b');

    $repository->register($a);
    $repository->register($b);

    expect($repository->all())->toHaveCount(2)
        ->and($repository->find('a'))->toBe($a)
        ->and($repository->find('b'))->toBe($b);
});
