# Documentation

Documentation for `happenv-com/laravel-true-modular` — a modular-monolith toolkit for Laravel.

## Start here

- [Getting started](getting-started.md) — install, swap the `Application`, create your first module.

## Building modules

- [Module builders](builders.md) — the fluent `Module` API and every `has*` feature.
- [Lifecycle hooks & report schemas](schema-hooks.md) — overridable provider hooks; report JSON schema.
- [Config merging](config-merging.md) — `hasConfig` / `mergesConfig` / `overwritesConfig` / `extendsConfig`.
- [Model extensions](model-extensions.md) — add attributes/relations to another module's model.

## Understanding the system

- [Architecture & runtime](architecture-runtime.md) — register → initialize → boot; the analysis layer.
- [Module dependencies](module-dependencies.md) — discovery, topological order, cycles, sorting.
- [CLI commands](cli-commands.md) — `module:graph` / `module:impact` / `module:why` / `module:list`.
- [Switching modules off](switching-modules-off.md) — keep a module in the repo without booting it.

## Working with the package

- [Best practices](best-practices.md) — conventions that keep a modular monolith healthy.
- [Anti-patterns](anti-patterns.md) — common mistakes and what to do instead.
- [Extending the package](extending-the-package.md) — add features, renderers, and data sources.
- [Testing](testing.md) — fixtures, helpers, and patterns for testing modules.
- [Agentic coding](agentic-coding.md) — working on the codebase with AI agents; the recommended workflow.
- [Laravel Boost integration](laravel-boost.md) — AI guidelines & skills shipped for coding agents.
