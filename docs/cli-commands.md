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
| `module:list` | List modules in dependency order (`--reverse`, `--simple`). |
| `module:seed` | Seed module database seeders in dependency order (`--module`, `--class`, `--show-order`). |
| `module:make:migration {module} {name}` | Scaffold a migration inside a module (`--create`, `--table`). |

`module:list` prints, for each module, its position in the topological order, its declared
dependencies and its path on disk. With `--reverse` the order is flipped (dependents first), which
matches teardown ordering.
