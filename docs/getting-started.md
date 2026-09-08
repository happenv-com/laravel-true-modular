# Getting started

`happenv-com/laravel-true-modular` turns a Laravel app into a modular monolith: modules are Composer
packages whose service providers boot in dependency order, with an extra `initialize()` lifecycle
phase for cross-module coordination.

Requires PHP 8.4+ and Laravel 12/13.

## 1. Install

```bash
composer require happenv-com/laravel-true-modular
```

The package's `KernelServiceProvider` is auto-discovered — it binds the module services and registers
the `module:*` console commands.

> **Quick path:** `php artisan true-modular:setup` automates steps 2–4 interactively — it swaps the
> `Application`, and can convert your existing `app/` folder into a `core` module. See
> [cli-commands.md](cli-commands.md#true-modularsetup). The manual steps below explain what it does.

## 2. Swap the Application

Module ordering and the `initialize()` phase live in a custom `Application`. Point `bootstrap/app.php`
at it:

```php
<?php

use Happenv\LaravelTrueModular\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(/* ... */)
    ->withMiddleware(/* ... */)
    ->create();
```

To use a custom module type identifier or a different modules directory, use the fluent
`ModularApplication` configurator. Its setters return `$this`, and `configure()` hands off to the
standard Laravel builder so the usual `->withRouting()` / `->create()` chain continues unchanged:

```php
<?php

use Happenv\LaravelTrueModular\ModularApplication;

return (new ModularApplication)
    ->composerType('acme-module')
    ->modulesDirectory('packages')
    ->modulesNamespace('Acme')        // root namespace for every module (used when scaffolding)
    ->coreModuleName('Core')          // segment appended to it for the core module, e.g. Acme\Core
    ->configure(basePath: dirname(__DIR__))
    ->withRouting(/* ... */)
    ->withMiddleware(/* ... */)
    ->create();
```

The same settings are also exposed as static methods on `Application`
(`Application::moduleComposerType()`, `Application::modulesDirectory()`,
`Application::modulesNamespace()`, `Application::coreModuleName()`) if you prefer to set them
directly — `ModularApplication` is a thin fluent wrapper over those.

## 3. Create a module

> **Quick path:** `php artisan module:make blog` scaffolds a ready-to-run module (composer.json,
> provider, config, and a `/blog/welcome` route) under your configured directory/namespace, registers
> it in `composer.json`, and offers to `composer update` it. That layout is a demo, not a contract —
> `php artisan vendor:publish --tag=true-modular-stubs` puts the stubs under your control, and the stub
> set decides which files a module gets. See
> [cli-commands.md](cli-commands.md#modulemake-name). The manual steps below explain the anatomy.

Modules live under `app-modules/` by default — change it with `ModularApplication::modulesDirectory()`
above. The defaults are owned by `Application` (`Application::DEFAULT_MODULES_DIRECTORY`,
`DEFAULT_COMPOSER_TYPE`, `DEFAULT_MODULES_NAMESPACE`); `ModuleRegistry` reads the configured value back via
`Application::getModulesDirectory()`. A module is a Composer package whose `composer.json` has
`type: "true-module"`:

```
app-modules/catalog/
├── composer.json
└── src/
    └── CatalogServiceProvider.php
```

```jsonc
// app-modules/catalog/composer.json
{
    "name": "acme/catalog",
    "type": "true-module",
    "require": {},
    "autoload": { "psr-4": { "Acme\\Catalog\\": "src/" } },
    "extra": { "laravel": { "providers": ["Acme\\Catalog\\CatalogServiceProvider"] } }
}
```

Register the module package with your root app (path repository + `composer require acme/catalog`, or
a path autoload) so its provider is discovered, then `composer dump-autoload`.

## 4. Write the provider

Extend `ModuleProvider` and describe the module with the fluent `Module` builder:

```php
namespace Acme\Catalog;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

class CatalogServiceProvider extends ModuleProvider
{
    public function configureModule(Module $module): void
    {
        $module
            ->name('acme/catalog')
            ->hasConfig('catalog')
            ->hasRoutes('web', 'api')
            ->hasViews()
            ->hasMigrations()
            ->runsMigrations();
    }
}
```

The only required call is `->name()`. The full catalogue of `has*` features is in
[builders.md](builders.md).

## 5. Verify

```bash
php artisan module:list
```

You should see your module and its position in dependency order. Add a dependency by requiring another
module in `composer.json`, and it will be sorted accordingly — see
[module-dependencies.md](module-dependencies.md).

## Where to next

- [builders.md](builders.md) — declare a module's features.
- [schema-hooks.md](schema-hooks.md) — lifecycle hooks you can override.
- [architecture-runtime.md](architecture-runtime.md) — the register → initialize → boot model.
- [best-practices.md](best-practices.md) / [anti-patterns.md](anti-patterns.md) — do's and don'ts.
- [cli-commands.md](cli-commands.md) — graph/impact/why analysis commands.
