<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Generators\ModuleGenerator;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->files = new Filesystem;
    $this->base = sys_get_temp_dir().'/tm-make-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->base);
    $this->files->put($this->base.'/composer.json', json_encode([
        'name' => 'acme/app',
        'require' => ['php' => '^8.4'],
    ], JSON_PRETTY_PRINT)."\n");

    $this->generate = fn (string $name = 'blog') => (new ModuleGenerator($this->files, $this->base))
        ->generate($name, 'app-modules', 'TrueModule', 'true-module');
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->base);
});

it('scaffolds the module files with the configured namespace and version', function (): void {
    $report = ($this->generate)();

    expect($report['package'])->toBe('true-module/blog')
        ->and($report['namespace'])->toBe('TrueModule\Blog')
        ->and($report['version'])->toBe('1.0.0')
        ->and($report['route'])->toBe('/blog/welcome');

    $module = $this->base.'/app-modules/blog';

    $composer = json_decode($this->files->get($module.'/composer.json'), true);
    expect($composer['name'])->toBe('true-module/blog')
        ->and($composer['type'])->toBe('true-module')
        ->and($composer['version'])->toBe('1.0.0')
        ->and($composer['autoload']['psr-4'])->toBe(['TrueModule\\Blog\\' => 'src/'])
        ->and($composer['extra']['laravel']['providers'])->toBe(['TrueModule\\Blog\\BlogServiceProvider']);
});

it('generates a provider declaring config and routes scoped to the module name', function (): void {
    ($this->generate)();

    $provider = $this->files->get($this->base.'/app-modules/blog/src/BlogServiceProvider.php');

    expect($provider)
        ->toContain('namespace TrueModule\Blog;')
        ->toContain('class BlogServiceProvider extends ModuleProvider')
        ->toContain("->name('blog')")
        ->toContain("->hasConfig('blog')")
        ->toContain("->hasRoutes('web')");
});

it('generates a welcome route + controller reading the module version config', function (): void {
    ($this->generate)();

    $module = $this->base.'/app-modules/blog';

    expect($this->files->get($module.'/config/blog.php'))->toContain("'version' => '1.0.0'");

    expect($this->files->get($module.'/routes/web.php'))
        ->toContain('use TrueModule\Blog\Http\Controllers\WelcomeModuleController;')
        ->toContain("Route::get('/blog/welcome', WelcomeModuleController::class);");

    expect($this->files->get($module.'/src/Http/Controllers/WelcomeModuleController.php'))
        ->toContain('namespace TrueModule\Blog\Http\Controllers;')
        ->toContain("return response(config('blog::blog.version'));");
});

it('registers the module in the root composer.json as a path package', function (): void {
    ($this->generate)();

    $composer = json_decode($this->files->get($this->base.'/composer.json'), true);

    expect($composer['repositories'][0])->toBe(['type' => 'path', 'url' => 'app-modules/*'])
        ->and($composer['require'])->toHaveKey('true-module/blog')
        ->and($composer['require']['true-module/blog'])->toBe('1.0.0');
});

it('kebab-cases multi-word names for the slug and studly-cases the namespace', function (): void {
    $report = ($this->generate)('BlogPosts');

    expect($report['slug'])->toBe('blog-posts')
        ->and($report['package'])->toBe('true-module/blog-posts')
        ->and($report['namespace'])->toBe('TrueModule\BlogPosts')
        ->and($report['route'])->toBe('/blog-posts/welcome')
        ->and($this->files->isDirectory($this->base.'/app-modules/blog-posts'))->toBeTrue();
});

it('aborts when the module already exists', function (): void {
    ($this->generate)();

    expect(fn () => ($this->generate)())->toThrow(RuntimeException::class);
});
