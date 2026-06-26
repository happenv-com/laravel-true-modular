<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

/**
 * Find PHP files that still reference App\ after conversion (e.g. under tests/),
 * so the command can point them out for manual follow-up.
 */
final class ScanLeftoverReferences extends Step
{
    /** Top-level directories skipped when scanning. */
    private const array EXCLUSIONS = ['vendor', 'node_modules', 'bootstrap', 'storage', '.git'];

    /** @return list<string> relative paths still referencing App\ */
    public function scan(string $modulesDirectory): array
    {
        $remaining = [];
        $excluded = [...self::EXCLUSIONS, $modulesDirectory];

        foreach ($this->files->directories($this->basePath) as $directory) {
            if (in_array(basename((string) $directory), $excluded, strict: true)) {
                continue;
            }

            foreach ($this->files->allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                if (str_contains($this->files->get($file->getPathname()), 'App\\')) {
                    $remaining[] = $this->relative($file->getPathname());
                }
            }
        }

        sort($remaining);

        return $remaining;
    }
}
