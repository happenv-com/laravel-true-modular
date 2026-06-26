<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Illuminate\Support\Collection;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

use function Safe\glob;

/**
 * Finds files in module directories while preserving module dependency order.
 *
 * This class is useful for discovering files like seeders, migrations, or any other
 * files that need to be processed in a specific order based on module dependencies.
 */
final readonly class ModuleFileFinder
{
    public function __construct(
        private ModuleRegistry $moduleRegistry,
    ) {}

    /**
     * Create a new instance with default ModuleRegistry.
     */
    public static function make(): self
    {
        return new self(ModuleRegistry::make());
    }

    /**
     * Find PHP files in a directory across all modules, ordered by module dependencies.
     *
     * @param  string  $directory  Relative path within module (e.g., 'database/seeders')
     * @param  string  $pattern  Glob pattern for files (default: '*.php')
     * @return Collection<int, array{file: string, module: string, class: class-string|null}>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    public function findFiles(string $directory, string $pattern = '*.php'): Collection
    {
        $order = $this->moduleRegistry->getTopologicalOrder();

        /** @phpstan-ignore return.type */
        return collect($order)
            ->flatMap(fn (string $moduleName): array => $this->getModuleFiles($moduleName, $directory, $pattern));
    }

    /**
     * Find PHP files in reverse dependency order (dependents first).
     *
     * @param  string  $directory  Relative path within module (e.g., 'database/seeders')
     * @param  string  $pattern  Glob pattern for files (default: '*.php')
     * @return Collection<int, array{file: string, module: string, class: class-string|null}>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    public function findFilesReverse(string $directory, string $pattern = '*.php'): Collection
    {
        $order = $this->moduleRegistry->getReverseTopologicalOrder();

        /** @phpstan-ignore return.type */
        return collect($order)
            ->flatMap(fn (string $moduleName): array => $this->getModuleFiles($moduleName, $directory, $pattern));
    }

    /**
     * Find PHP classes in a directory across all modules, ordered by module dependencies.
     *
     * Only returns files where the class actually exists.
     *
     * @param  string  $directory  Relative path within module (e.g., 'database/seeders')
     * @param  string  $namespaceSegment  Namespace segment matching directory (e.g., 'Database\Seeders')
     * @return Collection<int, array{class: class-string, module: string, file: string}>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    public function findClasses(string $directory, string $namespaceSegment): Collection
    {
        /** @phpstan-ignore return.type */
        return $this->findFiles($directory)
            ->map(fn (array $item): array => [
                ...$item,
                'class' => $this->resolveClassName($item['file'], $item['module'], $namespaceSegment),
            ])
            ->filter(fn (array $item): bool => class_exists($item['class']));
    }

    /**
     * Find PHP classes in reverse dependency order (dependents first).
     *
     * @param  string  $directory  Relative path within module (e.g., 'database/seeders')
     * @param  string  $namespaceSegment  Namespace segment matching directory (e.g., 'Database\Seeders')
     * @return Collection<int, array{class: class-string, module: string, file: string}>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    public function findClassesReverse(string $directory, string $namespaceSegment): Collection
    {
        /** @phpstan-ignore return.type */
        return $this->findFilesReverse($directory)
            ->map(fn (array $item): array => [
                ...$item,
                'class' => $this->resolveClassName($item['file'], $item['module'], $namespaceSegment),
            ])
            ->filter(fn (array $item): bool => class_exists($item['class']));
    }

    /**
     * Get files grouped by module in dependency order.
     *
     * @param  string  $directory  Relative path within module
     * @param  string  $pattern  Glob pattern for files
     * @return Collection<string, array<string>>
     *
     * @throws CircularDependencyException
     * @throws FilesystemException
     * @throws JsonException
     */
    public function findFilesGroupedByModule(string $directory, string $pattern = '*.php'): Collection
    {
        $order = $this->moduleRegistry->getTopologicalOrder();

        /** @phpstan-ignore return.type */
        return collect($order)
            ->mapWithKeys(function (string $moduleName) use ($directory, $pattern): array {
                $files = $this->getModuleFiles($moduleName, $directory, $pattern);

                return [$moduleName => array_column($files, 'file')];
            })
            ->filter(fn (array $files): bool => $files !== []);
    }

    /**
     * Get files from a specific module's directory.
     *
     * @return array<int, array{file: string, module: string, class: class-string|null}>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function getModuleFiles(string $moduleName, string $directory, string $pattern): array
    {
        $modulePath = $this->moduleRegistry->getModulePath($moduleName);

        if ($modulePath === null) {
            return [];
        }

        $fullPath = $modulePath.'/'.$directory;

        if (! is_dir($fullPath)) {
            return [];
        }

        $files = glob($fullPath.'/'.$pattern);

        return array_map(
            fn (string $file): array => [
                'class' => null,
                'file' => $file,
                'module' => $moduleName,
            ],
            $files
        );
    }

    /**
     * Resolve the fully qualified class name from a file path.
     *
     * @return class-string
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function resolveClassName(string $filePath, string $moduleName, string $namespaceSegment): string
    {
        $fileName = pathinfo($filePath, PATHINFO_FILENAME);
        $namespace = $this->getModuleNamespace($moduleName);

        return $namespace.$namespaceSegment.'\\'.$fileName;
    }

    /**
     * Get the root namespace for a module from its autoload config.
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function getModuleNamespace(string $moduleName): string
    {
        return $this->moduleRegistry->getModuleNamespace($moduleName) ?? '';
    }
}
