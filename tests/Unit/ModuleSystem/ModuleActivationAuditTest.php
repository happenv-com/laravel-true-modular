<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleActivation;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleActivationAudit;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

function audit(string $envValue): ModuleActivationAudit
{
    $_ENV[ModuleActivation::ENV_KEY] = $envValue;

    $registry = new ModuleRegistry(appModulesFixture());

    return new ModuleActivationAudit(
        $registry,
        new ModuleActivation($registry, dirname(appModulesFixture()), 'myapp'),
        dirname(appModulesFixture()),
    );
}

afterEach(function (): void {
    unset($_ENV[ModuleActivation::ENV_KEY], $_SERVER[ModuleActivation::ENV_KEY]);
});

it('reports no problems when nothing is disabled', function (): void {
    expect(audit('')->problems())->toBe([]);
});

it('rejects a name that resolves to no known module', function (): void {
    $problems = audit('myapp/nope')->problems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('myapp/nope');
});

it('rejects disabling a module that declares itself not disablable', function (): void {
    expect(implode("\n", audit('core')->problems()))->toContain('disablable');
});

it('rejects leaving an enabled module depending on a disabled one', function (): void {
    // Fixture graph: kernel <- core <- pim <- sale <- amazon.
    $problems = implode("\n", audit('pim')->problems());

    expect($problems)->toContain('myapp/sale')
        ->and($problems)->toContain('myapp/pim');
});

it('accepts a disabled leaf module, since nothing depends on it', function (): void {
    expect(audit('amazon')->problems())->toBe([]);
});

it('accepts a disabled module when every dependent is disabled too', function (): void {
    expect(audit('myapp/pim,myapp/sale,myapp/amazon')->problems())->toBe([]);
});

it('never cascades on its own — one entry short is still a problem', function (): void {
    $problems = implode("\n", audit('myapp/pim,myapp/sale')->problems());

    expect($problems)->toContain('myapp/amazon');
});
