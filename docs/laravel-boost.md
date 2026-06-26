# Laravel Boost integration

This package ships [Laravel Boost](https://laravel.com/docs/boost) resources so AI coding agents
understand the modular-monolith architecture and generate correct code for it. When a project that
depends on this package runs `php artisan boost:install` (or `boost:update --discover`), Boost
detects and installs them automatically — no configuration required.

## What ships

| Resource | Path in this package | Boost type |
| --- | --- | --- |
| Architecture overview | `resources/boost/guidelines/core.blade.php` | **AI guideline** — loaded upfront |
| Detailed development patterns | `resources/boost/skills/modular-monolith-development/SKILL.md` | **Agent skill** — loaded on demand |

- The **guideline** is always present in the agent's context. It anchors the agent: this is a modular
  monolith, modules live under `app-modules/`, providers extend `ModuleProvider`, declare features via
  the `Module` builder, and respect the register → initialize → boot lifecycle.
- The **skill** (`modular-monolith-development`) is activated on demand when the agent works on module
  code. It contains the detailed patterns: module anatomy, the declarative builder API, lifecycle phase
  placement, model extensions, config merging, dependency analysis, and the do/don't list.

Both point agents at the full docs under `vendor/happenv-com/laravel-true-modular/docs/`.

## Keeping them in sync

The Boost resources intentionally summarize the same rules documented in this `docs/` directory —
[builders.md](builders.md), [schema-hooks.md](schema-hooks.md),
[architecture-runtime.md](architecture-runtime.md), [model-extensions.md](model-extensions.md),
[config-merging.md](config-merging.md), [best-practices.md](best-practices.md), and
[anti-patterns.md](anti-patterns.md). When you change a convention in the package, update the matching
section of `core.blade.php` and `SKILL.md` so agents stay accurate.

> The guideline is a Blade file: wrap code examples in `@verbatim … @endverbatim` and avoid stray
> `{{ }}` / `@directive` syntax in prose, or Boost will try to compile them.
