<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Illuminate\Filesystem\Filesystem;

afterEach(function (): void {
    // Reset process-wide settings so they cannot leak into other tests.
    Application::modulesNamespace(Application::DEFAULT_MODULES_NAMESPACE);
});

it('falls back to the core module namespace when composer.json has no entry pointing at app/', function (): void {
    // tests/fixtures/composer.json has no `autoload` key at all — exactly the shape
    // `true-modular:setup` leaves behind once app/ has been converted into a module and its
    // "App\\": "app/" entry removed via ConfigureComposer::removeAppAutoload(). Before the fix,
    // Illuminate\Foundation\Application::getNamespace() has nothing in autoload.psr-4 to match
    // and throws RuntimeException('Unable to detect application namespace.') — breaking every
    // request, `route:list`, and `Model::factory()` on a converted app.
    $app = new Application(dirname(appModulesFixture()));

    expect($app->getNamespace())->toBe('TrueModule\Core\\');
});

it('builds the fallback from the configured modules namespace, not the bare default', function (): void {
    Application::modulesNamespace('Acme');

    $app = new Application(dirname(appModulesFixture()));

    expect($app->getNamespace())->toBe('Acme\Core\\');
});

it('keeps resolving the parent namespace unchanged for an application whose composer.json still autoloads an existing app/', function (): void {
    // Backward compatibility: an application that has NOT converted app/ into a module — the
    // overwhelming majority of installs, today and pre-existing — must resolve exactly as stock
    // Laravel does. The fix must never intercept a namespace the parent can already resolve.
    $files = new Filesystem;
    $base = sys_get_temp_dir().'/tm-namespace-'.bin2hex(random_bytes(6));
    $files->ensureDirectoryExists($base.'/app');
    $files->put($base.'/composer.json', json_encode([
        'name' => 'acme/app',
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
    ], JSON_PRETTY_PRINT));

    try {
        $app = new Application($base);

        expect($app->getNamespace())->toBe('App\\');
    } finally {
        $files->deleteDirectory($base);
    }
});

it('still resolves through the coincidental false-equals-false match when the App\\ entry points at a missing app/ directory', function (): void {
    // The fragile trap named in the bug report, documented rather than relied on: realpath()
    // returns false for both the non-existent app/ directory and the (also missing) target when
    // the stale entry is left in place, so `false === false` gives the parent a match anyway.
    // This pre-existing quirk survives the fix untouched, because the parent call succeeds here —
    // the fallback in the catch block never runs.
    $files = new Filesystem;
    $base = sys_get_temp_dir().'/tm-namespace-'.bin2hex(random_bytes(6));
    $files->ensureDirectoryExists($base);
    $files->put($base.'/composer.json', json_encode([
        'name' => 'acme/app',
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
    ], JSON_PRETTY_PRINT));

    try {
        $app = new Application($base);

        expect($app->getNamespace())->toBe('App\\');
    } finally {
        $files->deleteDirectory($base);
    }
});
