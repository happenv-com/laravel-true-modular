<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Setup\Steps;

/**
 * Empty bootstrap/providers.php — the moved AppServiceProvider is now
 * registered by the module's CoreServiceProvider, which auto-discovers.
 */
final class EmptyBootstrapProviders extends Step
{
    public function empty(): void
    {
        $path = $this->path('bootstrap/providers.php');

        if ($this->files->exists($path)) {
            $this->files->put($path, "<?php\n\nreturn [];\n");
        }
    }
}
