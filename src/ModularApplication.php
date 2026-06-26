<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular;

use Illuminate\Foundation\Configuration\ApplicationBuilder;

/**
 * Fluent entry point for bootstrapping a modular application, avoiding the
 * awkward chained static-call syntax on {@see Application}.
 *
 * Each setter records a package-level setting and returns $this; {@see configure()}
 * hands off to the standard Laravel {@see ApplicationBuilder}, so the usual
 * ->withRouting()/->withMiddleware()/->create() chain continues unchanged:
 *
 *     return (new ModularApplication)
 *         ->composerType('acme-module')
 *         ->modulesDirectory('packages')
 *         ->configure(basePath: dirname(__DIR__))
 *         ->withRouting(...)
 *         ->withMiddleware(...)
 *         ->create();
 */
final class ModularApplication
{
    /**
     * Set the composer `type` identifying a module package (default `true-module`).
     */
    public function composerType(string $type): self
    {
        Application::moduleComposerType($type);

        return $this;
    }

    /**
     * Set the directory (relative to the base path) scanned for modules (default `app-modules`).
     */
    public function modulesDirectory(string $directory): self
    {
        Application::modulesDirectory($directory);

        return $this;
    }

    /**
     * Set the root namespace under which modules live, e.g. `TrueModule` so the
     * core module is `TrueModule\Core` (default `TrueModule`). Used when scaffolding modules.
     */
    public function modulesNamespace(string $namespace): self
    {
        Application::modulesNamespace($namespace);

        return $this;
    }

    /**
     * Hand off to the standard Laravel application builder, having applied the
     * module settings above to the custom {@see Application}.
     */
    public function configure(?string $basePath = null): ApplicationBuilder
    {
        return Application::configure($basePath);
    }
}
