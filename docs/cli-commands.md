# CLI commands

The package ships Artisan commands for inspecting the module graph and for day-to-day module
work. They are registered automatically when running in the console.

## Architecture analysis

Three commands share one pipeline: build an immutable `ArchitectureIndex` from the tagged
sources, run an analyzer, and render the resulting report through a format-selected renderer.
All three accept `--format` and `--schema-version` and emit machine-readable JSON on demand —
see [architecture-runtime.md](architecture-runtime.md) for how the index is assembled and
[schema-hooks.md](schema-hooks.md) for the JSON schema contract.

### `module:graph`

Render the dependency graph as a tree (default), or for external tooling.

```bash
php artisan module:graph
php artisan module:graph --root=acme/catalog       # restrict to a subtree
php artisan module:graph --format=mermaid
php artisan module:graph --format=dot | dot -Tsvg -o graph.svg
php artisan module:graph --format=json
```

| Option | Description |
| --- | --- |
| `--root=<module>` | Restrict the graph to the subtree rooted at this module. |
| `--format=<fmt>` | `tree` (default), `text`, `mermaid`, `dot`, `json`. |
| `--schema-version=<n>` | Machine API schema version (default `1`). |

### `module:impact`

Show which modules would be affected by a change to a given module (its direct and transitive
dependents).

```bash
php artisan module:impact acme/catalog
php artisan module:impact acme/catalog --format=json
```

Useful in CI: fail a job when a change touches a module above a blast-radius threshold by parsing
the `total` field of the JSON output.

| Argument / option | Description |
| --- | --- |
| `module` | The module to analyze. |
| `--format=<fmt>` | `text` (default), `json`. |
| `--schema-version=<n>` | Machine API schema version (default `1`). |

### `module:why`

Explain why one module depends on another by printing the shortest dependency path.

```bash
php artisan module:why acme/checkout acme/catalog
php artisan module:why acme/checkout acme/catalog --format=json
```

| Argument / option | Description |
| --- | --- |
| `from` | The dependent module. |
| `to` | The dependency module. |
| `--format=<fmt>` | `text` (default), `json`. |
| `--schema-version=<n>` | Machine API schema version (default `1`). |

## Renderers & formats

Output format is chosen by matching a renderer's `format()` **and** `supports(report)`. Each report
type therefore only accepts the formats that make sense for it:

| Format | graph | impact | why | Notes |
| --- | :-: | :-: | :-: | --- |
| `text` | ✓ | ✓ | ✓ | Human-readable; per-report layout. |
| `tree` | ✓ | | | Box-drawing dependency tree. |
| `mermaid` | ✓ | | | `graph TD` for Markdown/Mermaid. |
| `dot` | ✓ | | | Graphviz `digraph`. |
| `json` | ✓ | ✓ | ✓ | Schema-wrapped, stable machine output. |

Asking for an unsupported `(format, report)` pair fails with a clear `UnsupportedFormatException`
message rather than producing empty output. Renderers are resolved from the container tag
`architecture.renderers`; you can add your own without touching the package — see
[extending-the-package.md](extending-the-package.md).

## Module management

| Command | Purpose |
| --- | --- |
| `true-modular:setup` | Interactively prepare the app to run as a modular monolith (see below). |
| `module:list` | List modules in dependency order (`--reverse`, `--simple`, `--format=table\|text\|json`). |
| `module:seed` | Seed module database seeders in dependency order (`--module`, `--class`, `--show-order`). |
| `module:make:migration {module} {name}` | Scaffold a migration inside a module (`--create`, `--table`). |

`module:list` prints, for each module, its position in the topological order, its declared
dependencies and its path on disk. With `--reverse` the order is flipped (dependents first), which
matches teardown ordering. The default `table` view uses the interactive console table; `--simple`
(or `--format=text`) prints a plain numbered list and `--format=json` emits the schema-wrapped
`modules` report — both flow through the same renderer pipeline as the analysis commands.

### `true-modular:setup`

A one-time setup command (built with [Laravel Prompts](https://laravel.com/docs/prompts)). It asks:

1. the Composer `type` used to identify modules (default `true-module`);
2. the directory modules live in (default `app-modules`);
3. whether to convert the current `app/` folder into a `core` module — and if so, the module's
   namespace (default `TrueModule`).

It always rewrites `bootstrap/app.php` to use `ModularApplication`, applying any non-default Composer
type, modules directory, and (when converting) module namespace via the fluent setters. If you opt
into the conversion, it also:

- moves `app/` into `{modules-dir}/core/src/` and rewrites the `App\` namespace to `{namespace}\Core`
  across the moved code plus `config/`, `database/` and `routes/`;
- scaffolds the module's `composer.json` and a `CoreServiceProvider` (which keeps your original
  `AppServiceProvider` working);
- registers the module as a Composer **path package** — prepends a `{modules-dir}/*` path repository
  (preserving any existing repositories) and `require`s `{vendor}/core` — and empties
  `bootstrap/providers.php`.

It leaves the root `composer.json` `autoload` untouched (the stale `"App\\": "app/"` entry only makes
Composer warn), but offers to remove that entry for you. After conversion the command prints any files
that still reference `App\` (e.g. under `tests/`) and reminds you to run `composer update {vendor}/core`
followed by `composer dump-autoload`. The command aborts without changes if `{modules-dir}/core`
already exists.
