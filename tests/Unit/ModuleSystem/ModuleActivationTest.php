<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleActivation;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

function activation(): ModuleActivation
{
    return new ModuleActivation(
        new ModuleRegistry(appModulesFixture()),
        dirname(appModulesFixture()),
        'myapp',
    );
}

function activationFile(): string
{
    return dirname(appModulesFixture()).'/'.ModuleActivation::FILE_NAME;
}

afterEach(function (): void {
    unset($_ENV[ModuleActivation::ENV_KEY], $_SERVER[ModuleActivation::ENV_KEY]);
    putenv(ModuleActivation::ENV_KEY);

    if (is_file(activationFile())) {
        unlink(activationFile());
    }
});

it('reports nothing disabled when neither channel is present', function (): void {
    expect(activation()->disabled())->toBe([])
        ->and(activation()->channel())->toBe('none');
});

it('reads the env channel and qualifies bare names', function (): void {
    $_ENV[ModuleActivation::ENV_KEY] = 'amazon, myapp/pim';

    expect(activation()->disabled())->toBe(['myapp/amazon', 'myapp/pim'])
        ->and(activation()->channel())->toBe('env');
});

it('treats an empty env value as an explicit empty list, not as absence', function (): void {
    file_put_contents(activationFile(), json_encode(['disabled' => ['myapp/amazon']]));
    $_ENV[ModuleActivation::ENV_KEY] = '';

    expect(activation()->disabled())->toBe([])
        ->and(activation()->channel())->toBe('env');
});

it('falls back to modules.json when the env key is absent', function (): void {
    file_put_contents(activationFile(), json_encode(['disabled' => ['amazon']]));

    expect(activation()->disabled())->toBe(['myapp/amazon'])
        ->and(activation()->channel())->toBe('file');
});

it('reads disablable from the module composer.json, defaulting to true', function (): void {
    expect(activation()->isDisablable('myapp/core'))->toBeFalse()
        ->and(activation()->isDisablable('core'))->toBeFalse()
        ->and(activation()->isDisablable('myapp/amazon'))->toBeTrue();
});

it('excludes a disabled module together with the packages it owns', function (): void {
    $_ENV[ModuleActivation::ENV_KEY] = 'amazon';

    expect(activation()->excludedPackages())->toBe(['myapp/amazon', 'myapp/amazon-sdk']);
});

it('excludes nothing at all when no module is disabled', function (): void {
    expect(activation()->excludedPackages())->toBe([]);
});

it('answers isDisabled for both the bare and the qualified name', function (): void {
    $_ENV[ModuleActivation::ENV_KEY] = 'myapp/amazon';

    expect(activation()->isDisabled('amazon'))->toBeTrue()
        ->and(activation()->isDisabled('myapp/amazon'))->toBeTrue()
        ->and(activation()->isDisabled('myapp/pim'))->toBeFalse();
});
