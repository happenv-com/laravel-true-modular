<?php

declare(strict_types=1);

it('runs a pure unit test without Laravel', function (): void {
    expect(1 + 1)->toBe(2);
});

it('exposes the fixture app-modules path', function (): void {
    expect(appModulesFixture())->toEndWith('tests/fixtures/app-modules')
        ->and(is_dir(appModulesFixture()))->toBeTrue();
});
