<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Generators;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleName;
use Happenv\LaravelTrueModular\Setup\Steps\ConfigureComposer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

use function Safe\json_encode;

/**
 * Scaffolds a new module under the configured modules directory / namespace and
 * registers it in the root composer.json. Separated from the command so the file
 * mutations can be unit-tested against a scaffold path.
 */
final readonly class ModuleGenerator
{
    public function __construct(
        private Filesystem $files,
        private string $basePath,
    ) {}

    /**
     * @return array{
     *     package: string,
     *     namespace: string,
     *     slug: string,
     *     path: string,
     *     version: string,
     *     route: string,
     *     files: list<string>,
     * }
     *
     * @throws RuntimeException when the target module directory already exists
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

        $files = [
            'composer.json' => $this->composerJson($package, $composerType, $moduleNamespace, $studly),
            'src/'.$studly.'ServiceProvider.php' => $this->serviceProvider($moduleNamespace, $studly, $slug),
            'src/Http/Controllers/WelcomeModuleController.php' => $this->controller($moduleNamespace, $slug),
            'config/'.$slug.'.php' => $this->config(),
            'routes/web.php' => $this->routes($moduleNamespace, $slug),
        ];

        $written = [];
        foreach ($files as $relative => $contents) {
            $target = $modulePath.'/'.$relative;
            $this->files->ensureDirectoryExists(dirname($target));
            $this->files->put($target, $contents);
            $written[] = $relativePath.'/'.$relative;
        }

        // Register the module in the root composer.json (path repository + require).
        (new ConfigureComposer($this->files, $this->basePath))->wire($modulesDirectory, $package);

        return [
            'package' => $package,
            'namespace' => $moduleNamespace,
            'slug' => $slug,
            'path' => $relativePath,
            'version' => Application::DEFAULT_MODULE_VERSION,
            'route' => '/'.$slug.'/welcome',
            'files' => $written,
        ];
    }

    private function composerJson(string $package, string $composerType, string $moduleNamespace, string $studly): string
    {
        $composer = [
            'name' => $package,
            'type' => $composerType,
            'version' => Application::DEFAULT_MODULE_VERSION,
            'autoload' => ['psr-4' => [$moduleNamespace.'\\' => 'src/']],
            'extra' => ['laravel' => ['providers' => [$moduleNamespace.'\\'.$studly.'ServiceProvider']]],
        ];

        return json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    private function serviceProvider(string $moduleNamespace, string $studly, string $slug): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$moduleNamespace};

        use Happenv\\LaravelTrueModular\\ModuleProvider\\Module;
        use Happenv\\LaravelTrueModular\\ModuleProvider\\ModuleProvider;

        class {$studly}ServiceProvider extends ModuleProvider
        {
            public function configureModule(Module \$module): void
            {
                \$module
                    ->name('{$slug}')
                    ->hasConfig('{$slug}')
                    ->hasRoutes('web');
            }
        }

        PHP;
    }

    private function controller(string $moduleNamespace, string $slug): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$moduleNamespace}\\Http\\Controllers;

        use Illuminate\\Http\\Response;

        class WelcomeModuleController
        {
            public function __invoke(): Response
            {
                return response(config('{$slug}::{$slug}.version'));
            }
        }

        PHP;
    }

    private function config(): string
    {
        $version = Application::DEFAULT_MODULE_VERSION;

        return <<<PHP
        <?php

        declare(strict_types=1);

        return [
            'version' => '{$version}',
        ];

        PHP;
    }

    private function routes(string $moduleNamespace, string $slug): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\\Support\\Facades\\Route;
        use {$moduleNamespace}\\Http\\Controllers\\WelcomeModuleController;

        Route::get('/{$slug}/welcome', WelcomeModuleController::class);

        PHP;
    }

    private function path(string $relative): string
    {
        return $this->basePath.'/'.ltrim($relative, '/');
    }
}
