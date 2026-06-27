---
name: modular-monolith-development
description: Build and modify features in a Laravel modular monolith powered by happenv-com/laravel-true-modular. Use when working in a project with an app-modules/ directory, modules whose composer.json has type "true-module", service providers extending ModuleProvider, or when adding/altering routes, configs, views, migrations, model extensions, or cross-module wiring in such a project.
---

# Modular monolith development (Laravel True Modular)

This project is a **modular monolith**: features live in self-contained modules (Composer packages),
not in `app/`. Modules boot in topological dependency order and gain an extra `initialize()` lifecycle
phase. Get the module boundaries, the declarative API, and the lifecycle phase right.

## When to use this skill

Activate when you are:

- creating a new module or adding a feature (routes, config, views, migrations, commands, …) to one;
- deciding **where** code goes (which lifecycle phase / which module);
- adding attributes or relations to another module's Eloquent model;
- wiring cross-module concerns (morph maps, permissions, gates, drivers, listeners);
- inspecting or reasoning about module dependencies.

## Module anatomy

A module is a Composer package under `app-modules/` (configurable). Required: `composer.json` with
`type: "true-module"` and a `name`. Shipped resources live one directory above the provider:

```
app-modules/catalog/
├── composer.json            # type: "true-module", require: { other modules }
├── config/                  # hasConfig / mergesConfig / overwritesConfig / extendsConfig
├── routes/                  # hasRoutes / hasBroadcastChannels / hasSchedules
├── database/migrations/     # hasMigrations / discoversMigrations
├── resources/{views,views-global,lang,dist,js/Pages}/
└── src/
    ├── CatalogServiceProvider.php
    └── Components/          # hasViewComponents (lives next to the provider)
```

```jsonc
// app-modules/catalog/composer.json
{
    "name": "acme/catalog",
    "type": "true-module",
    "require": { "acme/core": "*" },
    "autoload": { "psr-4": { "Acme\\Catalog\\": "src/" } },
    "extra": { "laravel": { "providers": ["Acme\\Catalog\\CatalogServiceProvider"] } }
}
```

Declare a dependency on another module by adding it to `require`. Keep the graph **acyclic** and keep
arrows pointing from dependents to dependencies (a low-level module must never require a high-level one).

## Declare features — never hand-wire

The provider extends `ModuleProvider` and implements `configureModule(Module $module)`. Use the fluent
`Module` builder; the matching processor runs each feature in the correct phase automatically. **Do not**
call `loadRoutesFrom`, `mergeConfigFrom`, `loadViewsFrom`, `loadMigrationsFrom`, `publishes`, `commands`,
etc. directly — declaring is what handles path resolution, console-only guards, missing-directory guards,
and config caching.

```php
namespace Acme\Catalog;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

class CatalogServiceProvider extends ModuleProvider
{
    public function configureModule(Module $module): void
    {
        $module
            ->name('acme/catalog')          // required; throws if missing
            ->hasConfig('catalog')          // config/catalog.php  -> config('catalog::catalog')
            ->mergesConfig('services')      // merge into existing config('services')
            ->hasRoutes('web', 'api')       // routes/web.php, routes/api.php
            ->hasViews()                    // resources/views, namespaced by short name
            ->hasMigrations()->runsMigrations()
            ->hasCommands(SyncCatalog::class)
            ->hasMorphMap('product', Product::class);
    }
}
```

Common builder methods: `hasConfig` / `mergesConfig` / `overwritesConfig` / `extendsConfig`,
`hasRoutes`, `hasBroadcastChannels`, `hasSchedules`, `hasViews` / `hasGlobalViews`, `hasViewComponents`,
`hasViewComposer`, `sharesDataWithAllViews`, `hasInertiaComponents`, `hasLivewireComponents`,
`hasTranslations`, `hasAssets`, `hasCommands` / `hasConsoleCommands`, `hasMigrations` /
`discoversMigrations` / `runsMigrations`, `hasMorphMap`, `hasPermissions`, `hasVoters`,
`hasEventListener`, `hasModelExtensions`, `hasModelBuilderExtensions`. Each `hasXs(...)` accepts spread
args or an array.

To add a feature the builder does not cover, add a `Concerns/Package/Has*` trait (stores the declaration)
**and** a matching `Concerns/PackageServiceProvider/Process*` trait (consumes it), and wire the
`process*()` call into the right phase — never edit package internals.

## Lifecycle: register → initialize → boot

The package inserts `initialize()` between `register()` and `boot()`. It runs **after every module is
registered, before any boots** — the window for cross-module coordination. The three phase methods are
`final`; hook in through the overridable no-op hooks instead:

| Put here | Hook | Why |
| --- | --- | --- |
| container bindings / singletons | `moduleRegistered()` | other modules may not be registered yet |
| morph maps, gates/permissions, drivers, model extensions, Livewire/Filament hooks | `initializingModule()` | every binding exists; nothing has booted |
| routes, views, commands, schedules, migrations | `bootingModule()` | normal boot-time wiring |

```php
public function moduleRegistered(): void
{
    $this->app->singleton(PriceCalculator::class);
}

public function initializingModule(): void
{
    Gate::policy(Product::class, ProductPolicy::class);
}
```

You may register closures around the initialize phase with `$this->initializing(...)` /
`$this->initialized(...)` (resolved through the container, so you can type-hint dependencies).

## bootstrap/app.php

```php
use Happenv\LaravelTrueModular\ModularApplication;

return (new ModularApplication)
    ->composerType('acme-module')        // optional: custom module composer type
    ->modulesDirectory('packages')       // optional: custom modules directory
    ->configure(basePath: dirname(__DIR__))
    ->withRouting(/* ... */)
    ->withMiddleware(/* ... */)
    ->create();
```

`ModularApplication` is a fluent wrapper over `Application::moduleComposerType()` /
`Application::modulesDirectory()`; `configure()` returns the standard Laravel `ApplicationBuilder`.

## Cross-module model extensions

To add an attribute or relation to a model owned by another module, declare a **model extension** in the
**downstream** module (the one that depends on the model's module), so the dependency arrow stays correct.

```php
// in the downstream module's provider
$module->hasModelExtensions(Product::class, ProductBillingExtension::class);

// the extension class
use Happenv\LaravelTrueModular\ModelExtension\ModelExtension;

final class ProductBillingExtension extends ModelExtension
{
    public function getFormattedPriceAttribute(): string  // attribute accessor
    {
        return Money::format($this->model->price);
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany  // relation
    {
        return $this->model->hasMany(Invoice::class);
    }
}
```

`$product->formatted_price` and `$product->invoices` then work as if defined on `Product`. Model
extensions are wired in the `initialize` phase.

## Config merging

Four strategies, processed in `initialize()` (skipped when config is cached):

- `hasConfig('x')` — publish under `config('<short>::x')`, no merge.
- `mergesConfig('x')` — shallow merge into existing `config('x')`.
- `overwritesConfig('x')` — recursively replace existing values (use sparingly).
- `extendsConfig('x')` — deep, type-aware merge: **lists are unioned + de-duplicated** (ignoring the
  overwrite flag), associative arrays merge recursively, scalars respect the overwrite flag. Keys are
  `ksort`ed, so do not rely on config key order afterward.

## Inspect dependencies

```bash
php artisan module:list                       # topological order (--reverse, --simple, --format=json)
php artisan module:graph --format=mermaid      # dependency graph (text/tree/mermaid/dot/json)
php artisan module:impact acme/core            # blast radius of a change (text/json)
php artisan module:why acme/checkout acme/core # shortest dependency path
```

Use `module:impact` before changing a low-level module, and `module:why` to confirm a dependency is
intended. The JSON output carries a versioned schema for CI gates.

When editing module `X`, work this loop to stay scoped:

1. `module:impact X` — the set of modules to **re-check** after the change (its dependents).
2. `module:why X <dep>` — confirm why an edge exists before relying on it.
3. Load **only** `X` plus the modules in its `composer.json` `require` — those are the only modules
   `X` may reference. Don't pull in dependents; they're for verification, not for the edit.
4. Edit inside `X`, then run `vendor/bin/phpstan analyse`. If the
   [boundary enforcer](https://github.com/happenv-com/laravel-true-modular-phpstan) is installed, a
   reference to a module not in `require` (or a new cycle) fails analysis — declare the dependency or
   don't cross the boundary.

## Do / don't

- DO put feature code in a module; DON'T add it to `app/`.
- DO declare features via the `Module` builder; DON'T call `loadRoutesFrom` / `mergeConfigFrom` / etc.
  directly.
- DO put cross-module wiring in `initializingModule()`; DON'T resolve other modules in `register()`.
- DO extend other modules' models from the downstream side; DON'T point a dependency upstream or create
  a cycle.
- DON'T edit package internals to add a renderer/source/feature — use the documented extension seams.

## Deeper reference

Full documentation ships with the package under `vendor/happenv-com/laravel-true-modular/docs/`:
`getting-started.md`, `builders.md`, `schema-hooks.md`, `architecture-runtime.md`,
`module-dependencies.md`, `model-extensions.md`, `config-merging.md`, `cli-commands.md`,
`agentic-coding.md`, `best-practices.md`, `anti-patterns.md`, `extending-the-package.md`, `testing.md`.
