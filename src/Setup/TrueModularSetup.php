<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup;

use Happenv\LaravelTrueModular\Setup\Steps\ConfigureBootstrapApp;
use Happenv\LaravelTrueModular\Setup\Steps\ConfigureComposer;
use Happenv\LaravelTrueModular\Setup\Steps\EmptyBootstrapProviders;
use Happenv\LaravelTrueModular\Setup\Steps\MoveApplicationToModule;
use Happenv\LaravelTrueModular\Setup\Steps\RewriteNamespace;
use Happenv\LaravelTrueModular\Setup\Steps\ScaffoldCoreModule;
use Happenv\LaravelTrueModular\Setup\Steps\ScanLeftoverReferences;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates the file mutations behind `true-modular:setup`, delegating each
 * concern to a focused {@see Steps\Step}. Separated from the command so the
 * whole flow can be unit-tested against a scaffold path.
 *
 * All paths are resolved relative to the given application base path.
 */
final readonly class TrueModularSetup
{
    /** Directories whose PHP files are rewritten when converting app/ into a module. */
    private const array REWRITE_DIRECTORIES = ['config', 'database', 'routes'];

    public function __construct(
        private Filesystem $files,
        private string $basePath,
    ) {}

    /**
     * Point bootstrap/app.php at our ModularApplication, applying any non-default
     * composer type / modules directory / modules namespace. Idempotent.
     */
    public function useModularApplication(string $composerType, string $modulesDirectory, ?string $modulesNamespace = null): bool
    {
        return (new ConfigureBootstrapApp($this->files, $this->basePath))
            ->swap($composerType, $modulesDirectory, $modulesNamespace);
    }

    /**
     * Move app/ into {modulesDir}/core, rewrite its namespace from App\ to
     * {namespace}\Core, register it as a Composer path package, and empty
     * bootstrap/providers.php.
     *
     * @return array{vendor: string, moduleNamespace: string, modulePath: string, rewritten: list<string>, remaining: list<string>}
     *
     * @throws RuntimeException when app/ is missing or the target already exists
     */
    public function convertAppToCoreModule(string $modulesDirectory, string $composerType, string $namespace): array
    {
        $vendor = Str::kebab(class_basename(str_replace('\\', '/', $namespace)));
        $moduleNamespace = trim($namespace, '\\').'\\Core';

        // 1. Move app/ -> {modulesDir}/core/src
        [$modulePath, $srcPath] = (new MoveApplicationToModule($this->files, $this->basePath))->move($modulesDirectory);

        // 2. Rewrite namespace in the moved code and the app's other code dirs
        $rewritten = (new RewriteNamespace($this->files, $this->basePath))->rewrite(
            [$srcPath, ...array_map($this->path(...), self::REWRITE_DIRECTORIES)],
            $namespace,
        );

        // 3. Scaffold the module (composer.json + a ModuleProvider)
        (new ScaffoldCoreModule($this->files, $this->basePath))->scaffold($modulePath, $srcPath, $vendor, $composerType, $moduleNamespace);

        // 4. Wire the module as a Composer path package
        (new ConfigureComposer($this->files, $this->basePath))->wire($modulesDirectory, $vendor.'/core');

        // 5. Empty bootstrap/providers.php (the module provider auto-discovers)
        (new EmptyBootstrapProviders($this->files, $this->basePath))->empty();

        return [
            'vendor' => $vendor,
            'moduleNamespace' => $moduleNamespace,
            'modulePath' => $modulesDirectory.'/core',
            'rewritten' => $rewritten,
            'remaining' => (new ScanLeftoverReferences($this->files, $this->basePath))->scan($modulesDirectory),
        ];
    }

    /**
     * Remove the now-unused `"App\\": "app/"` PSR-4 autoload entry from the root
     * composer.json. Offered to the user rather than done automatically.
     *
     * @return bool whether an entry was removed
     */
    public function removeAppAutoload(): bool
    {
        return (new ConfigureComposer($this->files, $this->basePath))->removeAppAutoload();
    }

    private function path(string $relative): string
    {
        return $this->basePath.'/'.ltrim($relative, '/');
    }
}
