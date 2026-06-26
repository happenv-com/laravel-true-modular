<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

/**
 * Replace the App\ root namespace with {namespace}\Core across PHP files in
 * the given directories (the moved code plus config / database / routes).
 */
final class RewriteNamespace extends Step
{
    /**
     * @param  list<string>  $directories  absolute directory paths to rewrite
     * @return list<string> relative paths of changed files
     */
    public function rewrite(array $directories, string $namespace): array
    {
        $changed = [];

        foreach ($directories as $directory) {
            $changed = [...$changed, ...$this->rewriteDirectory($directory, $namespace)];
        }

        return $changed;
    }

    /** @return list<string> relative paths of changed files */
    private function rewriteDirectory(string $directory, string $namespace): array
    {
        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $core = trim($namespace, '\\').'\\Core';
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
