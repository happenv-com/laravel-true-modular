<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

use function Safe\json_encode;

/**
 * Write the core module's composer.json and a CoreServiceProvider that keeps
 * the application's original AppServiceProvider working.
 */
final class ScaffoldCoreModule extends Step
{
    public function scaffold(string $modulePath, string $srcPath, string $vendor, string $composerType, string $moduleNamespace): void
    {
        $this->writeComposerJson($modulePath, $vendor, $composerType, $moduleNamespace);
        $this->writeServiceProvider($srcPath, $vendor, $moduleNamespace);
    }

    private function writeComposerJson(string $modulePath, string $vendor, string $composerType, string $moduleNamespace): void
    {
        $composer = [
            'name' => $vendor.'/core',
            'type' => $composerType,
            'autoload' => ['psr-4' => [$moduleNamespace.'\\' => 'src/']],
            'extra' => ['laravel' => ['providers' => [$moduleNamespace.'\\Providers\\CoreServiceProvider']]],
        ];

        $this->files->put(
            $modulePath.'/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    private function writeServiceProvider(string $srcPath, string $vendor, string $moduleNamespace): void
    {
        $providerDir = $srcPath.'/Providers';
        $this->files->ensureDirectoryExists($providerDir);

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$moduleNamespace}\\Providers;

        use Happenv\\LaravelTrueModular\\ModuleProvider\\Module;
        use Happenv\\LaravelTrueModular\\ModuleProvider\\ModuleProvider;

        class CoreServiceProvider extends ModuleProvider
        {
            public function configureModule(Module \$module): void
            {
                \$module->name('{$vendor}/core');
            }

            public function moduleRegistered(): void
            {
                // Keep the application's original AppServiceProvider working.
                if (class_exists(AppServiceProvider::class)) {
                    \$this->app->register(AppServiceProvider::class);
                }
            }
        }

        PHP;

        $this->files->put($providerDir.'/CoreServiceProvider.php', $stub);
    }
}
