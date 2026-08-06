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

    $this->generate = fn (string $name = 'blog'): array => (new ModuleGenerator($this->files, $this->base))
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

    $composer = json_decode($this->files->get($module.'/composer.json'), associative: true);
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

    $composer = json_decode($this->files->get($this->base.'/composer.json'), associative: true);

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

it('emits exactly the stub set, so removing a stub removes the file it produced', function (): void {
    $stubs = $this->base.'/custom-stubs';
    $this->files->ensureDirectoryExists($stubs.'/src');
    $this->files->put($stubs.'/composer.json.stub', '{"name": "{{ package }}"}');
    $this->files->put($stubs.'/src/{{studly}}ServiceProvider.php.stub', 'namespace {{ namespace }};');

    $report = (new ModuleGenerator($this->files, $this->base, $stubs))
        ->generate('blog', 'app-modules', 'TrueModule', 'true-module');

    expect($report['files'])->toBe([
        'app-modules/blog/composer.json',
        'app-modules/blog/src/BlogServiceProvider.php',
    ]);

    $module = $this->base.'/app-modules/blog';

    // The three files the packaged stubs would have produced are simply absent —
    // no post-scaffold deletion needed.
    expect($this->files->exists($module.'/src/Http/Controllers/WelcomeModuleController.php'))->toBeFalse()
        ->and($this->files->exists($module.'/routes/web.php'))->toBeFalse()
        ->and($this->files->exists($module.'/config/blog.php'))->toBeFalse()
        ->and($this->files->get($module.'/src/BlogServiceProvider.php'))->toBe('namespace TrueModule\Blog;');
});

it('reports no welcome route when the stubs did not produce one', function (): void {
    $stubs = $this->base.'/custom-stubs';
    $this->files->ensureDirectoryExists($stubs);
    $this->files->put($stubs.'/composer.json.stub', '{"name": "{{ package }}"}');

    $report = (new ModuleGenerator($this->files, $this->base, $stubs))
        ->generate('blog', 'app-modules', 'TrueModule', 'true-module');

    expect($report['route'])->toBeNull();
});

it('prefers stubs published into the application over the packaged ones', function (): void {
    $published = $this->base.'/'.ModuleGenerator::PUBLISHED_STUB_PATH;
    $this->files->ensureDirectoryExists($published);
    $this->files->put($published.'/README.md.stub', 'The {{ studly }} module.');

    $report = ($this->generate)();

    expect($report['files'])->toBe(['app-modules/blog/README.md'])
        ->and($this->files->get($this->base.'/app-modules/blog/README.md'))->toBe('The Blog module.');
});

it('replaces placeholders in paths as well as contents, in either spelling', function (): void {
    $stubs = $this->base.'/custom-stubs';
    $this->files->ensureDirectoryExists($stubs.'/config');
    $this->files->put($stubs.'/config/{{slug}}.php.stub', "return ['name' => '{{ slug }}'];");

    $report = (new ModuleGenerator($this->files, $this->base, $stubs))
        ->generate('BlogPosts', 'app-modules', 'TrueModule', 'true-module');

    expect($report['files'])->toBe(['app-modules/blog-posts/config/blog-posts.php'])
        ->and($this->files->get($this->base.'/app-modules/blog-posts/config/blog-posts.php'))
        ->toBe("return ['name' => 'blog-posts'];");
});

it('fails loudly when the stub directory holds no stubs', function (): void {
    $stubs = $this->base.'/empty-stubs';
    $this->files->ensureDirectoryExists($stubs);

    expect(fn (): array => (new ModuleGenerator($this->files, $this->base, $stubs))
        ->generate('blog', 'app-modules', 'TrueModule', 'true-module'))
        ->toThrow(RuntimeException::class, 'No module stubs found');
});
