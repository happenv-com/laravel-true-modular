# Module name resolution — accept bare (vendor-less) module names

- **Date:** 2026-06-27
- **Status:** Approved (design)
- **Scope:** Plan A of a two-plan effort. Plan B (display formatting + `--with-vendor`) is a separate,
  later spec and is explicitly out of scope here.

## Problem

Module commands today require the **full Composer name** (`vendor/name`) as the argument. Lookups are
exact `isset()` checks against keys read verbatim from each module's `composer.json` `name`
(`ModuleRegistry.php:86`, `ArchitectureIndex::assertKnown`). There is no normalization anywhere.

Consequences:

- `php artisan module:why amazon core` fails with `Unknown module [amazon]` — the user must type
  `happenv/amazon` (or whatever the vendor is). The README's bare-name examples are currently
  **inaccurate** and would error.
- Most users work only with their own local modules and should not have to retype their vendor
  prefix on every command.

## Goal

A bare module name (no `/`) is treated as a **local** module and qualified with the default vendor;
a name that contains `/` is treated as a **full package name** and passed through untouched. This is
resolved in **one** place (`ModuleLocator`) so every command behaves identically.

```
module:impact inventory       → happenv/inventory
module:impact sale            → happenv/sale
module:impact acme/catalog    → acme/catalog        (unchanged — has a slash)
module:impact filament/actions → filament/actions   (unchanged; resolves only if it's a known module)
```

### Non-goals (Plan B, separate spec)

- Display formatting (showing default-vendor modules as short names, external as full).
- The `--with-vendor` option.
- Short-name / fuzzy matching across arbitrary vendors, collision handling. We only do deterministic
  `<defaultVendor>/<name>` expansion.

## The rule

```
resolve(name):
    name      = strtolower(name)                                  // Composer names are lowercase
    qualified = name contains "/" ?  name  :  defaultVendor + "/" + name
    return locator.byComposerPackage(qualified)   // ?ModuleDescriptor, null if unknown
```

`defaultVendor = Str::kebab(class_basename(modulesNamespace))`. For the default namespace
`TrueModule` this is `true-module`; for `Application::modulesNamespace('Happenv')` it is `happenv`.
The locator only knows modules under `app-modules/` (`ModuleRegistry` globs `app-modules/*`), so
`resolve()` returns `null` for anything not discovered there.

**Resolution is case-insensitive.** Composer package names are lowercase, so `inventory`,
`Inventory`, and `INVENTORY` all resolve to `happenv/inventory`. The input is lowercased before
qualifying and lookup; this applies to slashed input too (`Acme/Catalog` → `acme/catalog`).

## Components

### 1. `ModuleName` — pure helper

New: `src/ModuleSystem/ModuleName.php`. No state, no dependencies, fully unit-testable.

```php
final class ModuleName
{
    public static function vendorFromNamespace(string $namespace): string;   // Str::kebab(class_basename(...))
    public static function qualify(string $name, string $vendor): string;    // lowercase; slash? as-is : "$vendor/$name"
}
```

`qualify()` is the single definition of the "has a slash → leave alone" rule, and lowercases its
input so callers don't each have to remember case-insensitivity.

### 2. `Application::getModulesVendor()` + generator de-duplication

- Add `Application::getModulesVendor(): string` (static) returning
  `ModuleName::vendorFromNamespace(self::getModulesNamespace())`. Single source of truth for the
  default vendor.
- Refactor `ModuleGenerator.php:44` (currently inlines the same `Str::kebab(class_basename(...))`
  derivation) to call `ModuleName::vendorFromNamespace($namespace)`, so scaffolding and resolution
  can never drift apart.

### 3. `ModuleLocator::resolve()` / `resolveOrFail()`

The locator is the single point of knowledge about modules, so it also owns name resolution and the
unknown-module error — no per-command logic, no shared trait.

- Add to the interface (`src/Architecture/Module/ModuleLocator.php`):

  ```php
  public function resolve(string $name): ?ModuleDescriptor;
  public function resolveOrFail(string $name): ModuleDescriptor;   // throws InvalidArgumentException
  ```

- Implement in `AppModulesLocator`:
  - `resolve()` — lowercase + `ModuleName::qualify()` with the locator's default vendor, then
    delegate to the existing `byComposerPackage()`.
  - `resolveOrFail()` — call `resolve()`; on `null` throw `InvalidArgumentException` with the message
    below. The locator knows both the typed input and the qualified form, so the message is built in
    one place.
- `AppModulesLocator` currently takes `(ModuleRegistry $registry, string $coreName = 'core')`. Add a
  `string $defaultVendor` constructor argument, bound in `KernelServiceProvider` from
  `Application::getModulesVendor()`. (Injected, not read statically, so it is overridable in tests.)

**Error message** (multi-line — shows what the user typed *and* how the runtime interpreted it, so a
typo vs. a wrong `defaultVendor` is immediately distinguishable):

```
Unknown module: amazon
Resolved to: happenv/amazon
Run `php artisan module:list` to see available modules.
```

When the input already contains a slash (nothing was qualified), the `Resolved to:` line is omitted.

### 4. Command integration

Each of the five module-name-taking commands injects `ModuleLocator` and calls a single line —
`$module = $locator->resolveOrFail($argument)` — then uses the returned `ModuleDescriptor` downstream.
No trait; the locator carries the behaviour.

| Command | Argument(s) resolved | Downstream consumer |
| --- | --- | --- |
| `module:impact` | `module` | analyzer → `ArchitectureIndex` via `ModuleDescriptor::$name` |
| `module:why` | `from`, `to` | analyzer → `ArchitectureIndex` |
| `module:graph` | `--root` | analyzer → `ArchitectureIndex` |
| `module:seed` | `--module` | `ModuleRegistry` via `ModuleDescriptor::$name`; replaces today's silent "No seeders found" with a hard unknown-module error |
| `module:make:migration` | `module` | uses `ModuleDescriptor::$path` exclusively (see below) |

The architecture commands already catch `InvalidArgumentException` in
`AbstractArchitectureCommand::handle()` and print it. `module:seed` and `module:make:migration` get
the same guard so the `resolveOrFail()` error surfaces cleanly.

**`ModuleDescriptor` is the single source of truth.** `module:make:migration` must build its target
from `ModuleDescriptor::$path` (e.g. `$path/database/migrations`), never by re-assembling
`app-modules/<name>/database/migrations` from strings. The descriptor already carries `path`,
`namespace`, `provider`, and the full package `name`; any command needing those reads them from the
descriptor, so a future `vendor/`-located module or an alternative locator can't drift the path logic.

`ArchitectureIndex` and `ModuleRegistry` stay **exact-match / pure** — no normalization leaks into the
data layer. Resolution lives only in the locator + command boundary.

## README changes

- The bare-name examples (`module:why amazon core`, `module:graph --root=sale`) become correct for a
  default-vendor application; keep them.
- Add one explicitly vendor-qualified example with a short note: external/Packagist-style modules are
  referenced by their full `vendor/name`, which signals they are not part of the local system.

## Testing

- **Unit (`ModuleName`):** `qualify` leaves slashed names untouched, qualifies bare names, lowercases
  input (`Inventory` / `INVENTORY` → `<vendor>/inventory`, `Acme/Catalog` → `acme/catalog`), handles
  multi-segment names; `vendorFromNamespace` maps `TrueModule → true-module`, namespaced inputs via
  `class_basename`.
- **Feature:** the fixtures use vendor `myapp` (`myapp/amazon`, `myapp/core`, …). Tests set
  `Application::modulesNamespace('Myapp')` so the derived vendor (`myapp`) matches the fixtures, then
  assert:
  - `module:impact amazon` resolves to `myapp/amazon` and succeeds;
  - the vendor-qualified form `myapp/amazon` still works;
  - an unknown bare name produces the friendly multi-line error (and a non-zero exit);
  - a non-default namespace changes the resolved vendor;
  - case-insensitive input (`Amazon`, `AMAZON`) resolves the same as `amazon`;
  - `module:seed --module=amazon` and `module:make:migration amazon …` accept the bare name;
  - `module:make:migration` writes into `ModuleDescriptor::$path/database/migrations`.

## Risks / notes

- Fixtures' vendor (`myapp`) ≠ the default (`true-module`); feature tests **must** configure the
  namespace, or they will look up the wrong vendor. Captured above.
- The locator only discovers `app-modules/` modules; truly external `vendor/` packages are not in the
  graph today. `resolve()` returning `null` for them is correct and unchanged by this work.
