<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

use Happenv\LaravelTrueModular\Application;

/**
 * Point bootstrap/app.php at our ModularApplication, applying any non-default
 * composer type / modules directory / modules namespace. Idempotent.
 *
 * Defaults are sourced from {@see Application} so they stay a single source of truth.
 */
final class ConfigureBootstrapApp extends Step
{
    public function swap(string $composerType, string $modulesDirectory, ?string $modulesNamespace = null): bool
    {
        $path = $this->path('bootstrap/app.php');

        if (! $this->files->exists($path)) {
            return false;
        }

        $content = $this->files->get($path);

        if (! str_contains($content, 'ModularApplication')) {
            $content = (string) preg_replace(
                '/^<\?php\s*\n/',
                "<?php\n\nuse Happenv\\LaravelTrueModular\\ModularApplication;\n",
                $content,
                1,
            );
        }

        $content = str_replace(
            'Application::configure(',
            '(new ModularApplication)'.$this->chain($composerType, $modulesDirectory, $modulesNamespace).'->configure(',
            $content,
        );

        $this->files->put($path, $content);

        return true;
    }

    /** Build the fluent setter chain, omitting calls that match the defaults. */
    private function chain(string $composerType, string $modulesDirectory, ?string $modulesNamespace): string
    {
        $chain = '';

        if ($composerType !== Application::DEFAULT_COMPOSER_TYPE) {
            $chain .= sprintf("->composerType('%s')", $composerType);
        }
        if ($modulesDirectory !== Application::DEFAULT_MODULES_DIRECTORY) {
            $chain .= sprintf("->modulesDirectory('%s')", $modulesDirectory);
        }
        if ($modulesNamespace !== null && $modulesNamespace !== Application::DEFAULT_MODULES_NAMESPACE) {
            $chain .= sprintf("->modulesNamespace('%s')", $modulesNamespace);
        }

        return $chain;
    }
}
