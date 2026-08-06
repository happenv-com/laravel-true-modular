<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleActivation;

afterEach(function (): void {
    unset($_ENV[ModuleActivation::ENV_KEY], $_SERVER[ModuleActivation::ENV_KEY]);
});

it('succeeds when the activation state is sound', function (): void {
    $_ENV[ModuleActivation::ENV_KEY] = '';

    $this->artisan('module:check')
        ->expectsOutputToContain('All modules enabled')
        ->assertSuccessful();
});

it('fails and names the offending module', function (): void {
    $_ENV[ModuleActivation::ENV_KEY] = 'myapp/nope';

    $this->artisan('module:check')
        ->expectsOutputToContain('myapp/nope')
        ->assertFailed();
});

it('reports the channel a sound disabled list came from', function (): void {
    $_ENV[ModuleActivation::ENV_KEY] = 'myapp/amazon';

    $this->artisan('module:check')
        ->expectsOutputToContain('Disabled via env: myapp/amazon')
        ->assertSuccessful();
});
