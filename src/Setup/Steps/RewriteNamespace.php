<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

use Happenv\LaravelTrueModular\Setup\TrueModularSetup;

/**
 * Replace the App\ root namespace with the given core module namespace (e.g.
 * {namespace}\Core) across PHP files in the given directories (the moved code
 * plus config / database / routes).
 */
final class RewriteNamespace extends Step
{
    /**
     * @param  list<string>  $directories  absolute directory paths to rewrite
     * @param  string  $moduleNamespace  the core module's own namespace, already composed
     *                                   (e.g. `TrueModule\Core`) — see {@see TrueModularSetup::convertAppToCoreModule()}
     * @return list<string> relative paths of changed files
     */
    public function rewrite(array $directories, string $moduleNamespace): array
    {
        $changed = [];

        foreach ($directories as $directory) {
            $changed = [...$changed, ...$this->rewriteDirectory($directory, $moduleNamespace)];
        }

        return $changed;
    }

    /** @return list<string> relative paths of changed files */
    private function rewriteDirectory(string $directory, string $moduleNamespace): array
    {
        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $core = trim($moduleNamespace, '\\');
        $changed = [];

        foreach ($this->files->allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $original = $this->files->get($file->getPathname());

            // `namespace App;` (root, no sub-namespace) has no trailing separator.
            $updated = (string) preg_replace('/\bnamespace\s+App\s*;/', 'namespace '.$core.';', $original);
            // Everything else: `App\Sub`, `use App\`, `\App\`, `App\\` in strings.
            $updated = str_replace('App\\', $core.'\\', $updated);

            if ($updated !== $original) {
                $this->files->put($file->getPathname(), $updated);
                $changed[] = $this->relative($file->getPathname());
            }
        }

        return $changed;
    }
}
