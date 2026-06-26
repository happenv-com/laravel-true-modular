<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

use RuntimeException;

/**
 * Move the host application's app/ directory into {modulesDir}/core/src,
 * guarding against a missing source or a pre-existing target.
 */
final class MoveApplicationToModule extends Step
{
    /**
     * @return array{0: string, 1: string} absolute [modulePath, srcPath]
     *
     * @throws RuntimeException when app/ is missing or the target already exists
     */
    public function move(string $modulesDirectory): array
    {
        $appPath = $this->path('app');
        $modulePath = $this->path($modulesDirectory.'/core');
        $srcPath = $modulePath.'/src';

        if (! $this->files->isDirectory($appPath)) {
            throw new RuntimeException('No app/ directory found to convert.');
        }

        if ($this->files->isDirectory($modulePath)) {
            throw new RuntimeException(sprintf('Target [%s/core] already exists; aborting to avoid data loss.', $modulesDirectory));
        }

        $this->files->makeDirectory($modulePath, 0755, recursive: true);
        $this->files->moveDirectory($appPath, $srcPath);

        return [$modulePath, $srcPath];
    }
}
