# Switching modules off

A module you are not ready to ship should stay in the repository and stop booting — not disappear from
`composer.json`. Removing it there leaves the directory on disk while its PSR-4 mapping vanishes, so
the first symptom is a bare `class not found` from somewhere unrelated (typically a test runner that
globs `app-modules/*/tests`).

The package therefore treats activation as its own concern: the code stays installed, and the
application decides which modules reach discovery.

## Two channels, one precedence rule

| channel | form | intended user |
| --- | --- | --- |
| `MODULES_DISABLED` | comma-separated list, e.g. `acme/workflows,acme/beta` | production and CI — a real environment variable, so it works in a container and needs no rebuild |
| `modules.json` | `{"disabled": ["acme/workflows"]}` in the application root | a developer machine; gitignore it |

**The environment key wins, and the lists are never merged.**

- `MODULES_DISABLED` set — *even to an empty string* — IS the whole list;
- otherwise `modules.json`, if present, IS the whole list;
- otherwise nothing is disabled.

Merging would make the effective state a function of two sources read together, and would take away
the ability to switch a module back **on** from the environment during an incident. `module:check`
prints which channel is in effect.

Names may be written bare (`workflows`) or fully qualified (`acme/workflows`); bare names are
qualified with your default vendor, exactly as in `module:graph` and friends. A name that resolves to
no known module is an **error**, not a silent no-op — a typo must not quietly disable nothing.

> **Trap:** `MODULES_DISABLED` read from a `.env` **file** is ignored when the configuration is
> cached — Laravel's `LoadEnvironmentVariables` bootstrapper exits early and never reads `.env`. Real
> environment variables (the container case) always work.

## What a disabled module loses

The manifest filter runs in `PackageManifest::getManifest()`, so a disabled package is cut from
discovery whole: its **service providers and its facade aliases** go together. Filtering only
providers would leave a dangling alias behind.

With no provider registered, the module contributes nothing — no config merge, no routes, no
migrations, no scheduled tasks, no panel plugins. Its tables stay in the schema with their data;
switching the module back on later simply runs the migrations it missed.

Nothing is disabled by default, and when the disabled list is empty the filter returns the manifest
untouched without ever reading the module registry — an application that configures neither channel
behaves exactly as it did before this feature existed.

## Module declarations

Two optional keys in the **module's** `composer.json`. They live there, rather than in the fluent
`Module` builder, because they are read before any service provider has registered.

```json
{
    "name": "acme/workflows",
    "type": "true-module",
    "extra": {
        "true-modular": {
            "disablable": false,
            "owns": ["acme/workflow-engine"]
        }
    }
}
```

### `disablable` (default `true`)

A module's statement about itself: `false` means it must never be switched off. Set it on your core
module. Formally it is redundant with the dependency rule below — everything requires core — but it
turns a cascade of dependency errors into one sentence.

### `owns`

Vendor packages this module is the **sole** owner of. They leave discovery together with the module.

Without this, disabling a module that wraps a third-party package leaves that package's own
auto-discovered provider booting: its migrations still run, its config still merges, its facade is
still aliased. The wrapper is off and the engine is on.

`extra.laravel.dont-discover` does not solve this. It is unconditional, so switching the module back
on in development would leave the engine undiscovered and the module subtly broken. `owns` is coupled
to the module's own state.

`module:check` rejects an `owns` entry that an **enabled** module (or the root application) also
requires — a contested package is not owned exclusively.

## Disabling never cascades

Switching off a module that an enabled module depends on is an **error**, listing exactly what to add:

```
Module [acme/sale] is enabled but depends on disabled [acme/pim]. Add [acme/sale] to the disabled
list too, or drop [acme/pim] from it — disabling never cascades on its own.
```

A silent cascade would let one line of configuration switch off a dozen modules with no trace of it
anywhere. Computing the cascade belongs to a user interface, which can show the affected set and ask
for confirmation; what reaches the runtime is always an explicit, complete list.

## `module:check`

```bash
php artisan module:check
```

Four validations:

1. every module directory is required by *some* `composer.json` — the root's or another module's
   (this is the orphaned-directory failure described at the top);
2. the disabled list names only known modules;
3. no disabled module declares `disablable: false`;
4. no enabled module depends on a disabled one, and no `owns` entry is contested.

Exit code 0 with a one-line summary, or 1 with one line per problem.

**Run it on deploy**, before the process starts serving — for example next to `config:cache` in a
container entrypoint. A bad disabled list must abort the release, not surface as a missing class on
the first request. Running it in CI as well keeps orphaned module directories from being merged.

## Seeing the state

```bash
php artisan module:list                   # adds a Status column
php artisan module:list --only-disabled   # just the switched-off ones
php artisan module:list --format=json     # each module carries an `enabled` flag
```
