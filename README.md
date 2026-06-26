# Laravel True Modular

[![Tests](https://github.com/happenv-com/laravel-true-modular/actions/workflows/run-tests.yml/badge.svg)](https://github.com/happenv-com/laravel-true-modular/actions)

Turn a Laravel application into a **modular monolith**. Modules are Composer packages that boot in
topological dependency order, with an extra `initialize()` lifecycle phase between `register()` and
`boot()` for cross-module coordination — plus architecture analysis commands for the module graph.

```php
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

## Why

1. **Topological provider ordering** — module service providers are sorted by their `composer.json`
   dependencies, so a module always boots after the modules it depends on.
2. **Enhanced lifecycle** — `register() → initialize() → boot()`. The `initialize()` phase runs after
   every module is registered but before any boots, the right window for morph maps, permissions,
   drivers, and Livewire/Filament hooks.
3. **Architecture tooling** — `module:graph`, `module:impact`, `module:why`, `module:list`, with
   `--format=json` for CI gates.

## Install

```bash
composer require happenv-com/laravel-true-modular
```

Then point `bootstrap/app.php` at the custom `Application` — see
[Getting started](docs/getting-started.md).

Requires PHP 8.3+ and Laravel 12/13.

## Documentation

Full documentation lives in [`docs/`](docs/README.md):

| | |
| --- | --- |
| [Getting started](docs/getting-started.md) | Install and create your first module. |
| [Module builders](docs/builders.md) | The fluent `Module` API and every feature. |
| [Lifecycle hooks & schemas](docs/schema-hooks.md) | Overridable provider hooks; report JSON schema. |
| [Config merging](docs/config-merging.md) | The four config strategies and merge semantics. |
| [Model extensions](docs/model-extensions.md) | Add attributes/relations to another module's model. |
| [Architecture & runtime](docs/architecture-runtime.md) | The lifecycle and the analysis layer. |
| [Module dependencies](docs/module-dependencies.md) | Discovery, ordering, cycles. |
| [CLI commands](docs/cli-commands.md) | Graph / impact / why / list. |
| [Best practices](docs/best-practices.md) · [Anti-patterns](docs/anti-patterns.md) | Do's and don'ts. |
| [Extending the package](docs/extending-the-package.md) | Add features, renderers, sources. |
| [Testing](docs/testing.md) | Fixtures, helpers, patterns. |
| [Laravel Boost](docs/laravel-boost.md) | AI guidelines & skills for coding agents. |

## Development

```bash
composer install
vendor/bin/pest             # tests (Pest 4)
vendor/bin/pint             # format
vendor/bin/phpstan analyse  # static analysis (level 6 + larastan)
```

## License

See [composer.json](composer.json) for license information.
