<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\Setup\TrueModularSetup;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->files = new Filesystem;
    $this->base = sys_get_temp_dir().'/tm-install-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->base);

    // Minimal but realistic host-app skeleton.
    $put = fn (string $rel, string $body) => tap(
        $this->base.'/'.$rel,
        function (string $path) use ($body): void {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $body);
        },
    );

    $put('bootstrap/app.php', <<<'PHP'
        <?php

        use Illuminate\Foundation\Application;

        return Application::configure(basePath: dirname(__DIR__))
            ->withRouting(web: __DIR__.'/../routes/web.php')
            ->create();
        PHP);

    $put('bootstrap/providers.php', "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");
    $put('app/Models/User.php', "<?php\n\nnamespace App\\Models;\n\nclass User {}\n");
    $put('app/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider {}\n");
    $put('config/auth.php', "<?php\n\nreturn ['providers' => ['users' => ['model' => App\\Models\\User::class]]];\n");
    $put('database/factories/UserFactory.php', "<?php\n\nnamespace Database\\Factories;\n\nuse App\\Models\\User;\n\nclass UserFactory {}\n");
    $put('routes/web.php', "<?php\n\nuse App\\Models\\User;\n");
    $put('tests/ExampleTest.php', "<?php\n\nuse App\\Models\\User;\n");
    $put('composer.json', json_encode([
        'name' => 'acme/app',
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
        'require' => ['php' => '^8.4'],
    ], JSON_PRETTY_PRINT)."\n");
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->base);
    // Reset process-wide settings so they cannot leak into other tests.
    Application::coreModuleName(Application::DEFAULT_CORE_MODULE_NAME);
});

it('swaps bootstrap/app.php to ModularApplication with non-default settings', function (): void {
    (new TrueModularSetup($this->files, $this->base))->useModularApplication('acme-module', 'packages');

    $content = $this->files->get($this->base.'/bootstrap/app.php');

    expect($content)
        ->toContain('use Happenv\LaravelTrueModular\ModularApplication;')
        ->toContain("(new ModularApplication)->composerType('acme-module')->modulesDirectory('packages')->configure(")
        ->not->toContain('Application::configure(');
});

it('omits default settings from the ModularApplication chain', function (): void {
    (new TrueModularSetup($this->files, $this->base))->useModularApplication('true-module', 'app-modules');

    expect($this->files->get($this->base.'/bootstrap/app.php'))
        ->toContain('(new ModularApplication)->configure(')
        ->not->toContain('composerType(')
        ->not->toContain('modulesDirectory(');
});

it('converts app/ into a core module', function (): void {
    $report = (new TrueModularSetup($this->files, $this->base))
        ->convertAppToCoreModule('packages', 'acme-module', 'TrueModule');

    // app/ moved into the module
    expect($this->files->isDirectory($this->base.'/app'))->toBeFalse()
        ->and($this->files->isDirectory($this->base.'/packages/core/src'))->toBeTrue();

    // namespace rewritten in moved code
    expect($this->files->get($this->base.'/packages/core/src/Models/User.php'))
        ->toContain('namespace TrueModule\Core\Models;');

    // namespace rewritten in config / database / routes
    expect($this->files->get($this->base.'/config/auth.php'))->toContain('TrueModule\Core\Models\User::class')
        ->and($this->files->get($this->base.'/database/factories/UserFactory.php'))->toContain('use TrueModule\Core\Models\User;')
        ->and($this->files->get($this->base.'/routes/web.php'))->toContain('use TrueModule\Core\Models\User;');

    // module composer.json + provider
    $moduleComposer = json_decode($this->files->get($this->base.'/packages/core/composer.json'), true);
    expect($moduleComposer['name'])->toBe('true-module/core')
        ->and($moduleComposer['type'])->toBe('acme-module')
        ->and($moduleComposer['version'])->toBe('1.0.0')
        ->and($moduleComposer['autoload']['psr-4'])->toBe(['TrueModule\\Core\\' => 'src/'])
        ->and($moduleComposer['extra']['laravel']['providers'])->toBe(['TrueModule\\Core\\CoreServiceProvider']);

    // The provider lives at the root of src/, like every module provider.
    expect($this->files->get($this->base.'/packages/core/src/CoreServiceProvider.php'))
        ->toContain('namespace TrueModule\Core;')
        ->toContain('use TrueModule\Core\Providers\AppServiceProvider;')
        ->toContain('extends ModuleProvider')
        ->and($this->files->exists($this->base.'/packages/core/src/Providers/CoreServiceProvider.php'))->toBeFalse();

    // report
    expect($report['vendor'])->toBe('true-module')
        ->and($report['moduleNamespace'])->toBe('TrueModule\Core');
});

it('wires the module as a composer path package without touching autoload', function (): void {
    (new TrueModularSetup($this->files, $this->base))
        ->convertAppToCoreModule('packages', 'acme-module', 'TrueModule');

    $composer = json_decode($this->files->get($this->base.'/composer.json'), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('App\\')          // left intact
        ->and($composer['repositories'][0])->toBe(['type' => 'path', 'url' => 'packages/*'])  // prepended
        ->and($composer['require']['true-module/core'])->toBe('1.0.0');
});

it('prepends the path repository, preserving existing repositories', function (): void {
    $existing = ['type' => 'composer', 'url' => 'https://example.test'];
    $composer = json_decode($this->files->get($this->base.'/composer.json'), true);
    $composer['repositories'] = [$existing];
    $this->files->put($this->base.'/composer.json', json_encode($composer, JSON_PRETTY_PRINT));

    (new TrueModularSetup($this->files, $this->base))
        ->convertAppToCoreModule('packages', 'acme-module', 'TrueModule');

    $repositories = json_decode($this->files->get($this->base.'/composer.json'), true)['repositories'];

    expect($repositories[0])->toBe(['type' => 'path', 'url' => 'packages/*'])
        ->and($repositories[1])->toBe($existing);
});

it('removes the App autoload entry only when asked', function (): void {
    $setup = new TrueModularSetup($this->files, $this->base);
    $setup->convertAppToCoreModule('packages', 'acme-module', 'TrueModule');

    expect($setup->removeAppAutoload())->toBeTrue()
        ->and(json_decode($this->files->get($this->base.'/composer.json'), true)['autoload']['psr-4'] ?? [])
        ->not->toHaveKey('App\\')
        // second call is a no-op
        ->and($setup->removeAppAutoload())->toBeFalse();
});

it('writes a non-default modules namespace into bootstrap/app.php', function (): void {
    (new TrueModularSetup($this->files, $this->base))
        ->useModularApplication('true-module', 'app-modules', 'Acme');

    expect($this->files->get($this->base.'/bootstrap/app.php'))
        ->toContain("(new ModularApplication)->modulesNamespace('Acme')->configure(");
});

it('empties bootstrap/providers.php', function (): void {
    (new TrueModularSetup($this->files, $this->base))
        ->convertAppToCoreModule('packages', 'acme-module', 'TrueModule');

    expect(trim($this->files->get($this->base.'/bootstrap/providers.php')))->toBe('<?php

return [];');
});

it('reports files that still reference App\\ after conversion', function (): void {
    $report = (new TrueModularSetup($this->files, $this->base))
        ->convertAppToCoreModule('packages', 'acme-module', 'TrueModule');

    expect($report['remaining'])->toContain('tests/ExampleTest.php');
});

it('aborts when the target module directory already exists', function (): void {
    $this->files->ensureDirectoryExists($this->base.'/packages/core');

    expect(fn () => (new TrueModularSetup($this->files, $this->base))
        ->convertAppToCoreModule('packages', 'acme-module', 'TrueModule'))
        ->toThrow(RuntimeException::class);
});

it('scaffolds the core module using the explicit default segment', function (): void {
    // Ties the assertion to the named constant instead of a hardcoded 'Core' string, so this
    // test tracks the constant's value rather than silently drifting from it.
    $report = (new TrueModularSetup($this->files, $this->base))
        ->convertAppToCoreModule('packages', 'acme-module', 'TrueModule');

    expect(Application::DEFAULT_CORE_MODULE_NAME)->toBe('Core')
        ->and($report['moduleNamespace'])->toBe('TrueModule\\'.Application::DEFAULT_CORE_MODULE_NAME);
});

it('scaffolds the core module from the configured segment, not a segment hardcoded independently of it', function (): void {
    // Before the fix, TrueModularSetup and RewriteNamespace each concatenated their own literal
    // '\Core' suffix — changing the package setting had no effect on the scaffolded output. This
    // proves the segment is actually read from Application::getCoreModuleName(), not re-hardcoded.
    Application::coreModuleName('Kernel');

    $report = (new TrueModularSetup($this->files, $this->base))
        ->convertAppToCoreModule('packages', 'acme-module', 'TrueModule');

    expect($report['moduleNamespace'])->toBe('TrueModule\Kernel');

    // The namespace rewrite (moved code, config/database/routes) picked up the same segment.
    expect($this->files->get($this->base.'/packages/core/src/Models/User.php'))
        ->toContain('namespace TrueModule\Kernel\Models;')
        ->and($this->files->get($this->base.'/config/auth.php'))->toContain('TrueModule\Kernel\Models\User::class');

    // The scaffolded composer.json and provider picked up the same segment too.
    $moduleComposer = json_decode($this->files->get($this->base.'/packages/core/composer.json'), true);
    expect($moduleComposer['autoload']['psr-4'])->toBe(['TrueModule\\Kernel\\' => 'src/'])
        ->and($moduleComposer['extra']['laravel']['providers'])->toBe(['TrueModule\\Kernel\\CoreServiceProvider']);
});
