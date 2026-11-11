---
name: modular-monolith-development
description: >-
  Build and modify features in a Laravel modular monolith powered by happenv-com/laravel-true-modular.
  Use when working in a project with an app-modules/ directory, modules whose composer.json has type
  "true-module", service providers extending ModuleProvider (configureModule(Module $module),
  moduleRegistered(), initializingModule(), bootingModule()), or bootstrap/app.php using
  ModularApplication; when adding routes, config, views, translations, migrations, seeders, commands,
  schedules, morph maps, health checks, model extensions (hasModelExtensions, ModelExtension) or
  builder mixins to a module; when scaffolding with module:make / module:make:migration / module:seed;
  when switching a module off (MODULES_DISABLED, modules.json, module:check); or when inspecting
  dependencies with module:list / module:graph / module:impact / module:why.
---

# Modular monolith development (Laravel True Modular)

This project is a **modular monolith**: features live in self-contained modules (Composer packages),
not in `app/`. Modules boot in topological dependency order and gain an extra `initialize()` lifecycle
phase. Get the module boundaries, the declarative API, and the lifecycle phase right.

## When to use this skill

Activate when you are:

- creating a new module or adding a feature (routes, config, views, migrations, commands, …) to one;
- deciding **where** code goes (which lifecycle phase / which module);
- adding attributes, relations or query methods to another module's Eloquent model;
- wiring cross-module concerns (morph maps, permissions, gates, drivers, listeners);
- inspecting or reasoning about module dependencies, switching a module off, or deploying.

## Module anatomy

A module is a Composer package under `app-modules/` (configurable). Required: `composer.json` with
`type: "true-module"` and a `name`. Shipped resources live one directory above the provider:

```
app-modules/catalog/
├── composer.json            # type: "true-module", require: { other modules }
├── config/                  # hasConfig / mergesConfig / overwritesConfig / extendsConfig
├── routes/                  # hasRoutes / hasBroadcastChannels / hasSchedules
├── database/migrations/     # hasMigrations / discoversMigrations
├── database/seeders/        # module:seed
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
A module may use another module's classes only if that module is in its **own** `require` (not
transitively); `require-dev` is for its tests.

### Creating modules

```bash
php artisan module:make catalog                         # app-modules/catalog, provider, config, routes
php artisan module:make:migration catalog create_products_table --create=products
php artisan vendor:publish --tag=true-modular-stubs     # customise what module:make generates
```

`module:make` registers the path repository and the `require` in the root `composer.json` and offers to
run `composer update`. Every `*.stub` in `stubs/true-modular/module/` becomes a file; a stub that declares
a file the module does not ship (`->hasConfig()` without `config/x.php`) fatals on boot.
`true-modular:setup` is the one-time conversion of an existing app (rewrites `bootstrap/app.php`,
optionally moves `app/` into a `core` module).

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
            ->name('catalog')               // required; the module's namespace for config/views/lang/morph aliases
            ->hasConfig('catalog')          // config/catalog.php   -> config('catalog::catalog')
            ->mergesConfig('services')      // merged into existing config('services')
            ->hasRoutes('web', 'api')       // routes/web.php, routes/api.php
            ->hasViews()                    // resources/views       -> view('catalog::index')
            ->hasTranslations()             // resources/lang        -> __('catalog::messages.saved')
            ->discoversMigrations()         // every file in database/migrations ...
            ->runsMigrations()              // ... and `migrate` runs them
            ->hasCommands(SyncCatalog::class)
            ->hasMorphMap('product', Product::class);   // alias "catalog::product"
    }
}
```

Builder methods (each `hasXs(...)` accepts spread args or an array):

- Config: `hasConfig`, `mergesConfig`, `overwritesConfig`, `extendsConfig` (see below).
- Routes and console: `hasRoute(s)`, `hasBroadcastChannel(s)` (`routes/channels.php`), `hasSchedule(s)`
  (`routes/schedule.php`, console only), `hasCommand(s)` (always registered), `hasConsoleCommand(s)`
  (console only).
- Views and UI: `hasViews(?namespace)`, `hasGlobalViews` (`resources/views-global`, unnamespaced),
  `hasViewComponents(prefix, ...classes)`, `hasViewComposer`, `sharesDataWithAllViews`,
  `hasLivewireComponents(name => class)` (needs Livewire), `hasInertiaComponents(?namespace)`,
  `hasTranslations`, `hasAssets` (`resources/dist`, published to `public/vendor/<name>`).
- Database: `hasMigration(s)(...names)`, `discoversMigrations(bool, path)`, `runsMigrations()`,
  `hasMorphMap(key, class)` or `hasMorphMap([key => class])`.
- Cross-module: `hasModelExtensions`, `hasModelBuilderExtensions`, `hasEventListener(events, listener)`,
  `hasPermissions` / `hasVoters` (both need `happenv-com/laravel-access-control` installed).
- Health: `hasHealthCheck(s)(...classes)` (consumed by `happenv-com/laravel-true-modular-health`;
  if installed, its `true-modular-health-development` skill covers it).

To add a feature the builder does not cover, add a `Concerns/Package/Has*` trait (stores the declaration)
**and** a matching `Concerns/PackageServiceProvider/Process*` trait (consumes it), and wire the
`process*()` call into the right phase — never edit package internals.

### Things the builder does not do for you

- **`name()` is not read from `composer.json`.** It is the prefix of everything the module publishes:
  config (`<name>::file`), views, translations, assets and Inertia pages use it minus anything up to a
  leading `laravel-`; morph aliases use it as written. `module:make` writes the slug (`->name('blog')` →
  `config('blog::blog')`).
  With a vendor-qualified name (`->name('acme/catalog')`, which `true-modular:setup` writes for `core`)
  the keys become `view('acme/catalog::index')` and `config('acme.catalog::catalog')`
  (the `/` in a config key turns into a dot). Pick the name once.
- **Morph aliases are prefixed with the module name.** `->name('catalog')->hasMorphMap('product', ...)`
  stores `catalog::product` in `*_type` columns. Call `name()` before `hasMorphMap()` (it throws
  otherwise), and treat renaming a module as a data migration.
- **`hasMigrations()` without names registers nothing.** Name the files (`->hasMigrations('create_products_table')`,
  no `.php`) or use `discoversMigrations()`. Without `runsMigrations()` migrations are only publishable
  (`vendor:publish --tag=<name>-migrations`); with it, `migrate` runs them from the module.
- **Route files are loaded as they are.** No middleware group or prefix is added: put
  `Route::middleware('web')->group(...)` in `routes/web.php` and `Route::prefix('api')->middleware('api')`
  in `routes/api.php` yourself.
- **Seeders** in `database/seeders/` are found as `<module namespace>\Database\Seeders\<File>`. Add that
  PSR-4 entry to the module's `composer.json`, or `module:seed` silently skips them.
  `module:seed [--module=acme/catalog] [--class=...] [--show-order]` runs them in dependency order.
- A declared directory that does not exist (views, migrations discovery, global views) is skipped with a
  log warning; a declared config or route **file** that does not exist fatals.

## Lifecycle: register → initialize → boot

The package inserts `initialize()` between `register()` and `boot()`. It runs **after every module is
registered, before any boots** — the window for cross-module coordination. The three phase methods are
`final`; hook in through the overridable no-op hooks, which run before and after each phase's processors:

| Phase | Before processors | After processors | Put here |
| --- | --- | --- | --- |
| register | `registeringModule()` | `moduleRegistered()` | container bindings / singletons; do not resolve other modules |
| initialize | `initializingModule()` | `moduleInitialized()` | gates/policies, drivers, macros, Livewire/Filament hooks (config, model extensions, morph maps, listeners are processed here) |
| boot | `bootingModule()` | `moduleBooted()` | normal boot-time work; routes, views, commands, migrations are processed here |

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

`bootingModule()` runs **before** the module's routes and views are loaded; use `moduleBooted()` for
code that needs them. You may register closures around the initialize phase with
`$this->initializing(...)` / `$this->initialized(...)` (resolved through the container, so you can
type-hint dependencies).

## bootstrap/app.php

```php
use Happenv\LaravelTrueModular\ModularApplication;

return (new ModularApplication)
    ->composerType('acme-module')        // optional: module composer type (default true-module)
    ->modulesDirectory('packages')       // optional: modules directory (default app-modules)
    ->modulesNamespace('Acme')           // optional: root namespace for module:make (default TrueModule)
    ->coreModuleName('Core')             // optional: core module segment (default Core)
    ->configure(basePath: dirname(__DIR__))
    ->withRouting(/* ... */)
    ->withMiddleware(/* ... */)
    ->create();
```

Never replace it with Laravel's `Application::configure()`; the custom `Application` does the provider
sorting and the initialize phase. To override container or lifecycle behaviour, extend
`Happenv\LaravelTrueModular\Application` and pass the subclass:
`->configure(basePath: dirname(__DIR__), application: AcmeApplication::class)`. The module settings are
class-wide statics, so the subclass shares them.

## Cross-module model extensions

To add an attribute or relation to a model owned by another module, declare a **model extension** in the
**downstream** module (the one that depends on the model's module), so the dependency arrow stays correct.

```php
// in the downstream module's provider
$module->hasModelExtensions(Product::class, ProductBillingExtension::class);
// or $module->hasModelExtensions([Product::class => ProductBillingExtension::class]);

// the extension class
use Happenv\LaravelTrueModular\ModelExtension\ModelExtension;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property Product $model */
final class ProductBillingExtension extends ModelExtension
{
    public function getFormattedPriceAttribute(): string  // attribute accessor
    {
        return Money::format($this->model->price);
    }

    public function invoices(): HasMany                   // relation
    {
        return $this->model->hasMany(Invoice::class);
    }
}
```

`$product->formatted_price` and `$product->invoices` then work as if defined on `Product`. Model
extensions are wired in the `initialize` phase. A method becomes a relation only if it is public,
declared on the extension class itself (not inherited) and has a **native return type** that is a
`Relation` subclass; a docblock-only or union return type is silently ignored.

Query methods for another module's builder: `hasModelBuilderExtensions([ProductBuilder::class =>
ProductBillingQueries::class])` mixes the class into the builder (`Builder::mixin()`), so its methods
must return closures, as with any Laravel mixin. Both builder and extension classes must exist at
declaration time.

In tests that register extensions by hand, call `AttributeResolversBag::flush()` in teardown.

## Config merging

Four strategies, processed in `initialize()` (skipped when config is cached):

- `hasConfig('x')` — publish under `config('<name>::x')`, no merge. Declaring the same file twice throws.
- `mergesConfig('x')` — shallow merge into existing `config('x')`.
- `overwritesConfig('x')` — recursively replace existing values (use sparingly).
- `extendsConfig('x')` — deep, type-aware merge: **lists are unioned + de-duplicated** (ignoring the
  overwrite flag), associative arrays merge recursively, scalars respect the overwrite flag. Keys are
  `ksort`ed, so do not rely on config key order afterward.

## Inspect dependencies

```bash
php artisan module:list                         # topological order (--reverse, --simple, --only-disabled, --format=json)
php artisan module:graph --format=mermaid        # dependency graph (tree/text/mermaid/dot/json)
php artisan module:graph --root=acme/catalog --once   # one subtree, every module on one line
php artisan module:impact acme/core              # blast radius of a change (text/json)
php artisan module:why acme/checkout acme/core   # shortest dependency path
```

Local modules can be named by short name (`catalog`) or full `vendor/name`. Use `module:impact` before
changing a low-level module, and `module:why` to confirm a dependency is intended. The JSON output
carries a versioned schema for CI gates.

When editing module `X`, work this loop to stay scoped:

1. `module:impact X` — the set of modules to **re-check** after the change (its dependents).
2. `module:why X <dep>` — confirm why an edge exists before relying on it.
3. Load **only** `X` plus the modules in its `composer.json` `require` — those are the only modules
   `X` may reference. Don't pull in dependents; they're for verification, not for the edit.
4. Edit inside `X`, then run `vendor/bin/phpstan analyse`. If
   `happenv-com/laravel-true-modular-phpstan` is installed, a reference to a module not in `require`
   (or a new cycle) fails analysis — its `true-modular-phpstan-development` skill explains each finding
   and how to fix it without pointing a dependency the wrong way.

## Switching a module off

A module that must not boot stays in `composer.json` and in the repository; **never** remove it from
`require` to disable it (the directory survives, its autoload mapping does not, and the failure shows
up as an unrelated `class not found`).

```bash
MODULES_DISABLED=acme/workflows   # production/CI; wins over modules.json, never merged with it
```

```json
// modules.json in the application root, gitignored — the developer-machine channel
{ "disabled": ["acme/workflows"] }
```

A disabled module loses its providers **and** its facade aliases; it contributes no config, routes,
migrations or scheduled tasks. In its own `composer.json` a module may declare
`extra.true-modular.disablable: false` (never switch me off) and `extra.true-modular.owns`
(vendor packages that leave discovery with me — otherwise a wrapped engine keeps booting its own
provider).

Disabling never cascades: switching off a module an enabled one depends on is an error naming what to
add. Validate with `php artisan module:check`, and run it on deploy before serving traffic.

## Deploying and caching

- `php artisan optimize` runs `true-modular:cache`, which writes the module scan and the topological
  order to `bootstrap/cache/true-modular.php`; `optimize:clear` runs `true-modular:clear`. The cache
  checks every module `composer.json`'s mtime and size, so a changed module never boots from a stale
  list and there is nothing to clear by hand after editing one.
- Run `php artisan module:check` before serving traffic.
- A cycle in the modules' `require` graph throws `CircularDependencyException` at boot.

## Do / don't

- DO put feature code in a module; DON'T add it to `app/`.
- DO declare features via the `Module` builder; DON'T call `loadRoutesFrom` / `mergeConfigFrom` / etc.
  directly.
- DO put cross-module wiring in `initializingModule()`; DON'T resolve other modules in `register()`.
- DO extend other modules' models from the downstream side; DON'T point a dependency upstream or create
  a cycle.
- DO list every module you use in your own `require`; DON'T rely on a transitive one.
- DON'T change a module's `name()` casually: config keys, view names and stored morph types follow it.
- DON'T edit package internals to add a renderer/source/feature — use the documented extension seams.
- DO switch a module off with `MODULES_DISABLED` / `modules.json`; DON'T remove it from `composer.json`.

## Deeper reference

Full documentation ships with the package under `vendor/happenv-com/laravel-true-modular/docs/`:
`getting-started.md`, `builders.md`, `schema-hooks.md`, `architecture-runtime.md`,
`module-dependencies.md`, `model-extensions.md`, `config-merging.md`, `cli-commands.md`,
`switching-modules-off.md`, `agentic-coding.md`, `best-practices.md`, `anti-patterns.md`,
`extending-the-package.md`, `testing.md`.
