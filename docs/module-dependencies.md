# Module dependencies

Modules declare their dependencies the ordinary Composer way — in `composer.json` `require`. The
package reads those declarations to discover modules, order them, and detect cycles.

## What makes a module

A directory under `app-modules/` (configurable) is a module when its `composer.json` has
`type: "true-module"` (overridable via `Application::moduleComposerType()`) and a `name`. A module
declares a dependency on another simply by requiring it:

```jsonc
// app-modules/sale/composer.json
{
    "name": "acme/sale",
    "type": "true-module",
    "require": {
        "acme/core": "*",
        "acme/pim": "*"
    },
    "autoload": { "psr-4": { "Acme\\Sale\\": "src/" } }
}
```

Only `require` entries that are *themselves* discovered modules count as module dependencies;
ordinary library requires are ignored for ordering.

## The discovery & graph pipeline

```
composer.json files ──▶ ModuleRegistry ──▶ DependencyGraph ──▶ analyzers / CLI
   (one filesystem scan)   (raw arrays)   (typed traversal)
```

- **`ModuleRegistry`** (`ModuleSystem/`) is the single component that touches the filesystem. It globs
  the modules directory, parses each `composer.json`, and exposes modules, dependencies, PSR-4
  namespaces, the raw dependency graph, and topological order. It is the foundation everything else
  builds on. The modules root defaults to `Application::DEFAULT_MODULES_DIRECTORY` (`app-modules`) and
  is configurable via `Application::modulesDirectory()` — see [getting-started.md](getting-started.md).
- **`DependencyGraph`** (`Architecture/Graph/`) is the typed, immutable graph value object used by
  the analysis layer: normalized adjacency, `dependencies()` / `dependents()`, transitive closures,
  shortest `path()`, `dependencyDepth()`, plus `topologicalOrder()` and `cycles()`.

Both delegate ordering and cycle detection to one shared algorithm, `ModuleSystem\Graph\TopologicalSort`,
so there is a single implementation of Kahn's sort and the DFS cycle finder.

## Topological order

`getTopologicalOrder()` returns modules so that **every dependency comes before its dependents**
(Kahn's algorithm). For the example graph `kernel ← core ← pim ← sale ← amazon`:

```
kernel, core, pim, sale, amazon
```

`getReverseTopologicalOrder()` is the exact reverse (dependents first) — useful for teardown and
seeding-after vs seeding-before scenarios. Inspect either with:

```bash
php artisan module:list
php artisan module:list --reverse
php artisan module:list --format=json
```

This ordering is what drives the runtime: service providers boot in dependency order. See
[architecture-runtime.md](architecture-runtime.md) for how `ServiceProviderSorter` applies it.

## Cycles

A dependency cycle (`a → b → a`) makes a valid order impossible. `getTopologicalOrder()` throws
`CircularDependencyException`, whose `cycles` property holds each distinct cycle (normalized to start
at its smallest member, so a cycle is reported once regardless of entry point). `module:list` prints
them:

```
Circular dependencies detected!
  acme/a -> acme/b -> acme/a
```

You can query the graph without booting via the analysis commands — `module:graph`, `module:impact`,
`module:why` — documented in [cli-commands.md](cli-commands.md).

## Matching classes to modules

Several places need "which module owns this class?" (e.g. the provider sorter). PSR-4 namespace
parsing lives once on `ModuleRegistry` (`getModuleNamespaces()` / `getModuleNamespace()`), and the
longest-prefix match is a single shared helper, `ModuleSystem\NamespaceMatcher::longestPrefix()`,
used by both the sorter and the architecture module locator.
