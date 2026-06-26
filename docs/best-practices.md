# Best practices

Conventions that keep a modular monolith maintainable as it grows.

## Put code in the right lifecycle phase

The register → initialize → boot split exists for a reason — use it.

- **`register()` / `moduleRegistered()`** — only container bindings and singletons. Never resolve
  another module's services here; they may not be registered yet.
- **`initialize()` / `initializingModule()`** — cross-module coordination that needs every binding
  present but must precede boot: morph maps, gates/permissions, driver registration, model extensions,
  Livewire/Filament hooks.
- **`boot()` / `bootingModule()`** — routes, views, commands, schedules, migrations.

Reaching across modules in the wrong phase is the most common source of order-dependent bugs. See
[architecture-runtime.md](architecture-runtime.md).

## Declare features; don't wire them by hand

Prefer the `Module` builder (`->hasRoutes()`, `->hasConfig()`, …) over calling `loadRoutesFrom`,
`mergeConfigFrom`, `publishes`, etc. directly. The processors handle path resolution
(`Module::vendorPath()`), console-only guards, missing-directory guards, and caching for you, and keep
every module consistent. The full list is in [builders.md](builders.md).

## Keep dependencies pointing one way

A module should depend only on modules it genuinely needs, and the graph must stay acyclic. Let the
`require` block in `composer.json` be the single source of truth for dependencies — the package derives
ordering from it. Check the shape regularly:

```bash
php artisan module:graph
php artisan module:why acme/checkout acme/catalog   # is this dependency intended?
php artisan module:impact acme/core                 # blast radius before changing a low-level module
```

## Extend other modules' models from the downstream side

To add an attribute or relation to another module's model, use
[model extensions](model-extensions.md) declared in the *augmenting* (downstream) module. This keeps
the dependency arrow correct — the module that knows about the new behaviour depends on the one that
owns the model, never the reverse.

## Combine shared config with `extendsConfig`

When several modules contribute to one shared config structure (middleware lists, permission maps),
use `extendsConfig()` so lists are unioned rather than overwritten. Know the merge contract —
especially that lists ignore `$overwrite` and keys are re-sorted — before relying on it. See
[config-merging.md](config-merging.md).

## Name modules and namespaces consistently

`composer.json` `name` (e.g. `acme/catalog`) is the module identity used throughout the graph; the
short name (`catalog`) is used for view/translation/asset namespaces. Keep the PSR-4 namespace aligned
with the package so the provider sorter can match providers to modules by namespace prefix.

## Use the machine API in CI

Every analysis command supports `--format=json` with a versioned schema. Gate merges on architectural
facts — e.g. fail when `module:impact --format=json` reports a blast radius above a threshold, or when
`module:graph` reveals an unexpected edge. See [cli-commands.md](cli-commands.md) and
[schema-hooks.md](schema-hooks.md).

## Extend the package through its seams

Add module features, renderers, and architecture sources via the documented extension points rather
than editing package internals — see [extending-the-package.md](extending-the-package.md).
