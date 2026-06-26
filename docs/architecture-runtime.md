# Architecture & runtime

This package replaces Laravel's `Application` so that module service providers boot in dependency
order and gain an extra lifecycle phase. It also exposes a read-only analysis layer over the module
graph. The two are independent: the runtime drives booting, the analysis layer answers questions.

## The lifecycle: register → initialize → boot

Standard Laravel has two provider phases: `register()` then `boot()`. This package inserts a third,
`initialize()`, **between** them:

```
all providers register()  ──▶  all providers initialize()  ──▶  all providers boot()
```

`initialize()` runs after **every** provider has registered (so every binding exists) but before
**any** provider boots. That is exactly the window for cross-module coordination that needs other
modules present but must happen before boot-time work:

| Phase | Put here | Why |
| --- | --- | --- |
| `register()` | container bindings, singletons | classic registration; don't resolve other modules yet |
| `initialize()` | morph maps, gates/permissions, driver registration, Livewire/Filament hooks, model extensions | all bindings exist; nothing has booted |
| `boot()` | routes, views, commands, schedules, migrations | normal boot-time wiring |

`ModuleProvider` orchestrates this for you: `register()`, `initialize()` and `boot()` are `final` and
call the feature processors in a fixed, safe order. You hook in through the overridable
[lifecycle hooks](schema-hooks.md) (`moduleRegistered()`, `initializingModule()`, …), not by
overriding the phases.

## The custom Application

`Happenv\LaravelTrueModular\Application` extends `Illuminate\Foundation\Application` and overrides
`boot()` to:

1. Resolve `ServiceProviderSorter` and reorder `$this->serviceProviders` into module dependency order.
2. Fire `initializing` callbacks, run `initialize()` on every provider, fire `initialized` callbacks.
3. Run `boot()` on every provider (the usual Laravel booting callbacks still fire).

Wire it up in `bootstrap/app.php` — see [getting-started.md](getting-started.md). To change the
module composer type, call `Application::moduleComposerType('acme-module')` before `configure()`.

### Provider ordering

`ServiceProviderSorter` partitions providers into module providers and everything else. Non-module
providers keep their original relative order and run **first**; module providers are sorted into
topological order (dependencies before dependents) using `ModuleTree`'s order. A provider is matched
to its module by longest PSR-4 namespace prefix (`NamespaceMatcher`). See
[module-dependencies.md](module-dependencies.md) for the ordering itself.

## The analysis layer

Separate from the runtime, the `Architecture/` namespace builds an immutable snapshot of the module
graph for the CLI and any tooling you add.

```
ArchitectureSource(s) ──▶ contributions ──▶ IndexAccumulator ──▶ ArchitectureIndex
   (tagged 'architecture.sources')                                  (modules + DependencyGraph)
```

- **Sources** are tagged `architecture.sources`. The built-in `ComposerArchitectureSource` yields a
  `ModulesContribution` (typed `ModuleDescriptor`s) and a `DependenciesContribution` (edges).
- **Contributions apply themselves.** Each contribution implements
  `applyTo(MutableArchitectureIndex $index)` — `ModulesContribution` calls `addModules()`,
  `DependenciesContribution` calls `addDependencies()`. `ArchitectureIndexBuilder` just iterates and
  calls `applyTo()`; it never switches on contribution types, so a new *kind* of contribution plugs
  in without touching the builder.
- **The index** exposes module descriptors and a `DependencyGraph`, queried by the analyzers
  (`GraphAnalyzer`, `ImpactAnalyzer`, `WhyAnalyzer`) and rendered by tagged renderers.

Adding sources, contributions, and renderers is covered in
[extending-the-package.md](extending-the-package.md); the commands in
[cli-commands.md](cli-commands.md).
