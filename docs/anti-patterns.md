# Anti-patterns

Common mistakes in a modular monolith and what to do instead.

## Resolving another module in `register()`

```php
// ✗ another module's binding may not exist yet
public function moduleRegistered(): void
{
    $this->app->make(PaymentGateway::class)->configure(...);
}
```

`register()` runs while providers are still registering. Anything that touches another module belongs
in `initialize()`, which runs after all registrations.

```php
// ✓ every binding exists; nothing has booted
public function initializingModule(): void
{
    $this->app->make(PaymentGateway::class)->configure(...);
}
```

## Circular dependencies

If `a` requires `b` and `b` requires `a`, no boot order is valid — `getTopologicalOrder()` throws
`CircularDependencyException`. Don't try to "break the tie" with runtime ordering hacks; restructure
instead: extract the shared piece into a third lower-level module both depend on. Diagnose with:

```bash
php artisan module:list          # prints the detected cycles
php artisan module:why a b
```

## Wrong dependency direction (upstream knowing about downstream)

A low-level module (`core`) must not `require` a high-level one (`billing`). If `core` needs a
behaviour that lives in `billing`, the design is inverted. Move the behaviour: have `billing` extend
`core`'s model via a [model extension](model-extensions.md), or introduce an interface in `core` that
`billing` implements. Check direction with `module:graph` / `module:impact`.

## Hand-wiring instead of declaring

```php
// ✗ bypasses path resolution, console guards, caching, consistency
public function bootingModule(): void
{
    $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    $this->mergeConfigFrom(__DIR__.'/../config/catalog.php', 'catalog');
}
```

```php
// ✓ declare it
public function configureModule(Module $module): void
{
    $module->name('acme/catalog')->hasRoutes('web')->mergesConfig('catalog');
}
```

A declaration records *what* the module has; the package then runs the matching **feature processor**
(the `process*()` method paired with each `has*` feature) automatically, in the **correct lifecycle
phase**. `mergesConfig('catalog')` is consumed by `processConfigs()` during `initialize()`;
`hasRoutes('web')` is consumed by `processRoutes()` during `boot()`. You declare it once and never
think about *when* or *how* it runs — phase ordering, path resolution, console-only guards,
missing-directory guards and config caching are all handled for you. The full mapping of feature →
phase is in [schema-hooks.md](schema-hooks.md).

You *can* still wire something by hand — sometimes you must, for behaviour no `has*` feature covers.
When you do, do it **deliberately**: there is no single right place, so decide which phase fits. Ask
whether the work needs other modules to already be registered (then `initializingModule()` /
`initialized()`), or whether it is ordinary boot-time wiring (then `bootingModule()` / `booted()`) —
don't default to `register()` or the first hook you reach for. See
[architecture-runtime.md](architecture-runtime.md) for what each phase guarantees.

## Overusing `overwritesConfig`

`overwritesConfig()` recursively replaces host values and is rarely what you want — it makes a module
silently clobber app or sibling-module config. Prefer `mergesConfig()` for defaults and
`extendsConfig()` for shared structures you contribute to. Reserve `overwritesConfig()` for the rare
case where the module genuinely owns the whole key. See [config-merging.md](config-merging.md).

## Relying on config key order after `extendsConfig`

`ConfigMerger` `ksort`s every level, so insertion order is not preserved. Never write logic that
depends on the ordering of keys in an extended config array.

## Editing package internals to add a feature

Don't fork the package to add a renderer, data source, or module feature. Each has an extension seam
(container tags, trait pairs) — see [extending-the-package.md](extending-the-package.md). Editing
internals means painful upgrades; the seams are stable.

## `hasViews()` / `discoversMigrations()` without the directory

Declaring a feature whose directory is absent on a fresh checkout used to risk a boot crash. The
processors guard against a missing directory, but the cleaner habit is: only declare what the module
actually ships, and commit at least an empty, tracked directory when a feature is declared.
