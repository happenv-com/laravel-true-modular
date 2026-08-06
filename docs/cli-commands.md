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
| `module:make {name}` | Scaffold a new module under the configured directory/namespace (see below). |
| `module:list` | List modules in dependency order (`--reverse`, `--simple`, `--only-disabled`, `--format=table\|text\|json`). |
| `module:check` | Validate module activation: orphaned directories, the disabled list, dependencies, owned packages. |
| `module:seed` | Seed module database seeders in dependency order (`--module`, `--class`, `--show-order`). |
| `module:make:migration {module} {name}` | Scaffold a migration inside a module (`--create`, `--table`). |

`module:list` prints, for each module, its position in the topological order, its declared
dependencies and its path on disk. With `--reverse` the order is flipped (dependents first), which
matches teardown ordering. The default `table` view uses the interactive console table; `--simple`
(or `--format=text`) prints a plain numbered list and `--format=json` emits the schema-wrapped
`modules` report — both flow through the same renderer pipeline as the analysis commands.

`--only-disabled` narrows the list to the modules the current environment switches off, and every
view carries an activation state (a `Status` column in the table, an `enabled` flag in JSON).

### `module:check`

Validates module activation and exits non-zero on the first problem set:

1. every module directory is required by *some* `composer.json` — the root's or another module's;
2. the disabled list names only known modules;
3. no disabled module declares `disablable: false`;
4. no enabled module depends on a disabled one, and no `owns` entry is contested.

Run it on deploy, before the process starts serving — a bad disabled list must abort the release, not
surface as a missing class on the first request. See
[Switching modules off](switching-modules-off.md).

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

### `module:make {name}`

Scaffold a new module under the configured modules directory and namespace
(`Application::modulesDirectory()` / `modulesNamespace()`, or their `ModularApplication` wrappers). The
name is kebab-cased for the slug/package and studly-cased for the namespace — `module:make BlogPosts`
produces the package `{vendor}/blog-posts` with namespace `{namespace}\BlogPosts`.

For `php artisan module:make blog` (defaults `app-modules` / `TrueModule`) it writes:

```
app-modules/blog/
├── composer.json                                  # name true-module/blog, type true-module, version 1.0.0
├── config/blog.php                                # ['version' => '1.0.0']
├── routes/web.php                                 # GET /blog/welcome -> WelcomeModuleController
└── src/
    ├── BlogServiceProvider.php                    # ->name('blog')->hasConfig('blog')->hasRoutes('web')
    └── Http/Controllers/WelcomeModuleController.php
```

The controller returns `config('blog::blog.version')`, so hitting `/blog/welcome` renders the module's
version (`1.0.0` by default — set in both `composer.json` and `config/blog.php`).

It then registers the module in the root `composer.json` (prepends the `{modules-dir}/*` path repository
if missing and `require`s the new package) and offers to run `composer update {vendor}/{slug}` for you to
install it and auto-discover its provider. The requirement is inserted where Composer's own
`sort-packages` would keep it, so the scaffolding does not show up as an out-of-order diff hunk; existing
entries are never reordered. The command aborts without changes if the module directory already exists.

#### Customising what a module gets — publish the stubs

The layout above is the package's demo module, not a fixed contract. **The generated file set is the stub
set**: every `*.stub` becomes one file at the same relative path minus the extension, so adding a stub
adds a file and deleting one removes it.

```bash
php artisan vendor:publish --tag=true-modular-stubs
```

That copies the stubs to `stubs/true-modular/module/`, which `module:make` then uses instead of the
package's. Delete `src/Http/Controllers/WelcomeModuleController.php.stub`, `routes/web.php.stub` and
`config/{{slug}}.php.stub` (and drop the matching `->hasConfig()` / `->hasRoutes()` from the provider
stub — a module declaring a file it does not ship fatals on boot) to get a bare module; edit
`composer.json.stub` to carry your own `require`, `require-dev`, PSR-4 entries for factories and tests,
and anything else every module in your app has. This replaces deleting the same files after every run.

Placeholders are substituted in file **contents and paths**, in either spelling (`{{ slug }}` or
`{{slug}}`, the latter so filenames stay space-free):

| Placeholder | `module:make BlogPosts` under vendor `acme` / namespace `Acme` |
|---|---|
| `{{ package }}` | `acme/blog-posts` |
| `{{ vendor }}` | `acme` |
| `{{ type }}` | the configured Composer type, e.g. `true-module` |
| `{{ slug }}` | `blog-posts` |
| `{{ studly }}` | `BlogPosts` |
| `{{ namespace }}` | `Acme\BlogPosts` |
| `{{ namespaceEscaped }}` | `Acme\\BlogPosts` — for embedding in a `.json` stub, which has no single-backslash escape |
| `{{ version }}` | `1.0.0` |

The report's `route` key is only set when the stubs produced `routes/web.php`; the command omits the
"exposed GET …" line otherwise. If you need more than custom stubs, rebind
`Happenv\LaravelTrueModular\Generators\ModuleGenerator` in a service provider — the command resolves it
from the container.
