<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Generators;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleName;
use Happenv\LaravelTrueModular\Setup\Steps\ConfigureComposer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileInfo;

/**
 * Scaffolds a new module under the configured modules directory / namespace and
 * registers it in the root composer.json. Separated from the command so the file
 * mutations can be unit-tested against a scaffold path.
 *
 * The generated file SET is the stub directory: every `*.stub` under it becomes
 * one file in the new module, at the same relative path minus the extension.
 * Adding a stub adds a file, deleting one removes it — so an application whose
 * module convention differs from the package's demo (no welcome controller, a
 * richer composer.json, a tests/ skeleton) expresses that by publishing the
 * stubs and editing them, instead of deleting the same files after every run.
 *
 * Stub resolution, first match wins:
 *   1. the `$stubDirectory` passed to the constructor,
 *   2. `stubs/true-modular/module` in the application (`vendor:publish --tag=true-modular-stubs`),
 *   3. the package's own `stubs/module`.
 *
 * Placeholders are replaced in both the file CONTENTS and the file PATH, in
 * either spelling — `{{ slug }}` or `{{slug}}` — so a stub can be named
 * `config/{{slug}}.php.stub` without a space in the filename.
 */
final readonly class ModuleGenerator
{
    /** Relative to the application base path; where `vendor:publish` puts the stubs. */
    public const string PUBLISHED_STUB_PATH = 'stubs/true-modular/module';

    public function __construct(
        private Filesystem $files,
        private string $basePath,
        private ?string $stubDirectory = null,
    ) {}

    /** The stubs shipped with the package, used when nothing overrides them. */
    public static function packageStubDirectory(): string
    {
        return dirname(__DIR__, 2).'/stubs/module';
    }

    /**
     * @return array{
     *     package: string,
     *     namespace: string,
     *     slug: string,
     *     path: string,
     *     version: string,
     *     route: string|null,
     *     files: list<string>,
     * }
     *
     * @throws RuntimeException when the target module directory already exists,
     *                          or the stub directory holds no stubs
     */
    public function generate(string $name, string $modulesDirectory, string $namespace, string $composerType): array
    {
        $slug = Str::kebab(Str::studly($name));
        $studly = Str::studly($name);
        $vendor = ModuleName::vendorFromNamespace($namespace);
        $package = $vendor.'/'.$slug;
        $moduleNamespace = trim($namespace, '\\').'\\'.$studly;
        $relativePath = $modulesDirectory.'/'.$slug;
        $modulePath = $this->path($relativePath);

        if ($this->files->isDirectory($modulePath)) {
            throw new RuntimeException(sprintf('Module [%s] already exists at [%s].', $slug, $relativePath));
        }

        $replacements = [
            'package' => $package,
            'vendor' => $vendor,
            'type' => $composerType,
            'slug' => $slug,
            'studly' => $studly,
            'namespace' => $moduleNamespace,
            // JSON has no single-backslash escape, so a namespace embedded in a
            // .json stub needs the doubled form. Offered as its own placeholder
            // rather than escaped on the fly: only the stub knows it is JSON.
            'namespaceEscaped' => str_replace('\\', '\\\\', $moduleNamespace),
            'version' => Application::DEFAULT_MODULE_VERSION,
        ];

        $written = [];
        foreach ($this->stubs() as $stub => $relative) {
            $target = $this->replace($relative, $replacements);
            $absolute = $modulePath.'/'.$target;

            $this->files->ensureDirectoryExists(dirname($absolute));
            $this->files->put($absolute, $this->replace($this->files->get($stub), $replacements));

            $written[] = $relativePath.'/'.$target;
        }

        // Register the module in the root composer.json (path repository + require).
        (new ConfigureComposer($this->files, $this->basePath))->wire($modulesDirectory, $package);

        return [
            'package' => $package,
            'namespace' => $moduleNamespace,
            'slug' => $slug,
            'path' => $relativePath,
            'version' => Application::DEFAULT_MODULE_VERSION,
            // Only claimed when the stubs actually produced the route file; a stub
            // set without it would otherwise have the command advertise a URL that
            // does not exist.
            'route' => in_array($relativePath.'/routes/web.php', $written, strict: true) ? '/'.$slug.'/welcome' : null,
            'files' => $written,
        ];
    }

    /**
     * Absolute stub path => path inside the module, still carrying placeholders.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException
     */
    private function stubs(): array
    {
        $directory = $this->resolveStubDirectory();

        $stubs = [];
        foreach ($this->files->allFiles($directory) as $file) {
            if ($file->getExtension() !== 'stub') {
                continue;
            }

            $stubs[$file->getPathname()] = $this->relativeTarget($file, $directory);
        }

        if ($stubs === []) {
            throw new RuntimeException(sprintf('No module stubs found in [%s].', $directory));
        }

        // The report lists files in a stable order regardless of how the
        // filesystem happened to enumerate the directory.
        asort($stubs);

        return $stubs;
    }

    private function resolveStubDirectory(): string
    {
        foreach ([$this->stubDirectory, $this->path(self::PUBLISHED_STUB_PATH)] as $candidate) {
            if ($candidate !== null && $this->files->isDirectory($candidate)) {
                return $candidate;
            }
        }

        return self::packageStubDirectory();
    }

    private function relativeTarget(SplFileInfo $file, string $directory): string
    {
        $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($directory))), '/');

        return substr($relative, 0, -strlen('.stub'));
    }

    /** @param  array<string, string>  $replacements */
    private function replace(string $subject, array $replacements): string
    {
        $search = [];
        $replace = [];

        foreach ($replacements as $key => $value) {
            $search[] = '{{ '.$key.' }}';
            $replace[] = $value;
            $search[] = '{{'.$key.'}}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $subject);
    }

    private function path(string $relative): string
    {
        return $this->basePath.'/'.ltrim($relative, '/');
    }
}
