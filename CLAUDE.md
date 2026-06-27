# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel package (`happenv-com/laravel-true-modular`, namespace `Happenv\LaravelTrueModular\`) that turns a Laravel app into a modular monolith. Modules are Composer packages living under `app-modules/` or in `vendor/`, identified by `composer.json` `type: "true-module"`. The package provides:

1. A custom `Application` that sorts module service providers in topological dependency order and adds an `initialize()` lifecycle phase between `register()` and `boot()`.
2. A `ModuleProvider` base class that auto-wires a module's assets, routes, configs, migrations, views, permissions, etc. from a fluent `Module` definition.
3. Architecture analysis commands (`module:graph`, `module:impact`, `module:why`, `module:list`) plus scaffolding/lifecycle commands (`true-modular:setup`, `module:make`, `module:make:migration`, `module:seed`).

A companion package `happenv-com/laravel-true-modular-phpstan` (separate repo) adds PHPStan rules that **enforce** module boundaries and type the runtime model extensions. The package also ships Laravel Boost resources under `resources/boost/` (a guideline + a `modular-monolith-development` skill) so AI agents understand the architecture.

PHP 8.3+, Laravel 12/13. Requires `thecodingmachine/safe` (use `Safe\` functions for filesystem/json/pcre).

## Commands

```bash
composer install                       # install deps
vendor/bin/pest                        # run all tests (Pest 4)
vendor/bin/pest tests/Feature/ModuleRegistryTest.php   # single file
vendor/bin/pest --filter "sorts providers"          # single test by name
vendor/bin/pest --testsuite Unit       # Unit or Feature suite (see phpunit.xml)
vendor/bin/pint                        # format (auto-runs in CI on push)
vendor/bin/phpstan analyse             # static analysis, level 6 + larastan
vendor/bin/rector process              # apply refactorings (dry-run: --dry-run)
```

There are no Composer script aliases — call the `vendor/bin/*` binaries directly. CI (`.github/workflows/`) runs Pint (auto-commits fixes), PHPStan, and the test matrix.

## Architecture

### Lifecycle: register → initialize → boot

`Application` (extends `Illuminate\Foundation\Application`) overrides `boot()` to:
1. Run `ServiceProviderSorter::sort()` on all registered providers. The Kahn topological sort itself lives in `ModuleRegistry::getTopologicalOrder()` (over module `composer.json` `require` deps); `ServiceProviderSorter` just orders the module providers by that result and keeps non-module providers in their original order, running them first.
2. Call `initialize()` on every provider (the new phase), then `boot()` on every provider.

`initialize()` runs **after all providers are registered, before any boots** — it's where cross-module coordination belongs: `Relation::morphMap()`, permission/gate registration, driver registration, Filament/Livewire hooks. Bindings go in `register()`; routes/views/commands go in `boot()`. See README.md for the full rationale and examples.

The module Composer type is `true-module` by default; override before `configure()` in `bootstrap/app.php` via `Application::moduleComposerType('acme-module')`. The modules directory and namespace are also configurable (`Application::modulesDirectory()`, `Application::modulesNamespace()`). The preferred wiring is the fluent `ModularApplication` wrapper (`src/ModularApplication.php`) — `(new ModularApplication)->composerType(...)->modulesDirectory(...)->configure(...)->create()` — which records those static settings and hands off to the standard `ApplicationBuilder`. `true-modular:setup` rewrites `bootstrap/app.php` to use it.

### Two parallel "module" concepts — do not confuse them

- **`src/ModuleProvider/`** — the runtime base class a module's own `ServiceProvider` extends. `ModuleProvider` is `abstract`; a module implements `configureModule(Module $module)` to declare its features fluently. The `Module` value object (`ModuleProvider/Module.php`) and the provider each compose ~20 traits in lockstep: every feature is a pair — a `Concerns/Package/Has*.php` trait (holds the declared config on `Module`) and a `Concerns/PackageServiceProvider/Process*.php` trait (consumes it during the lifecycle). **To add a module feature, add both traits and wire the `process*()` call into the right phase** in `ModuleProvider::register/initialize/boot()`. Config processing is skipped when `configurationIsCached()`.

- **`src/Architecture/`** + **`src/ModuleSystem/`** — read-only analysis of the module graph for the CLI commands. `ModuleSystem/ModuleRegistry` scans `base_path('app-modules')` for `composer.json` files and builds the dependency tree (throws `CircularDependencyException` on cycles). `Architecture/` builds an immutable `ArchitectureIndex` from tagged `ArchitectureSource`s (sources are container-tagged `'architecture.sources'`), queried by the analyzers (`GraphAnalyzer`, `ImpactAnalyzer`, `WhyAnalyzer`) and emitted through pluggable renderers (`text`, `json`, `tree`, `mermaid`, `dot`) container-tagged `'architecture.renderers'` and resolved via `RendererRegistry` (matched by `format()` + `supports()`). Note `module:list` also has a `table` format, rendered in-command (Symfony table), not a renderer.

### Wiring

`KernelServiceProvider` is the package entrypoint (auto-discovered via `extra.laravel.providers`). It binds `ModuleRegistry`, `ModuleFileFinder`, `ModuleLocator`, the architecture index/sources, and `RendererRegistry`, and registers the console commands in `boot()` (intentionally **not** `initialize()`, so `true-modular:setup` stays discoverable on a fresh install still running stock `Illuminate\Foundation\Application`). To add an architecture data source, bind it and add it to the `'architecture.sources'` tag. To add a renderer, implement `ArchitectureRenderer` and add it to the `'architecture.renderers'` tag.

`ModelExtension/` lets one module add attributes/relations to another module's Eloquent model (`processModelExtensions` / `processModelBuilderExtensions` run in `initialize()`).

`Config/ConfigMerger` implements `extendConfigs`: lists are merged + deduplicated regardless of the `overwrite` flag; scalars and type mismatches respect `overwrite` (see README table).

## Testing

Tests use `orchestra/testbench` (Pest). `tests/TestCase.php` points `applicationBasePath()` at `tests/fixtures/`, so `base_path('app-modules')` resolves to `tests/fixtures/app-modules/` — a set of fixture modules (`kernel`, `core`, `pim`, `sale`, `amazon`) with real `composer.json` dep declarations used to exercise the sorter and graph. `getEnvironmentSetUp` rebinds `ModuleRegistry` to that fixture path. Add new fixture modules there (and to the `classmap` autoload in `composer.json`) when testing graph/lifecycle behavior. Pest's `appModulesFixture()` helper (tests/Pest.php) returns that path.
