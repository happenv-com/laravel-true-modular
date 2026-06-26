# Config merging

A module ships config files under its `config/` directory and declares how each should combine with
the host application's config. There are four declarations, processed in the **initialize** phase
(and skipped entirely when the application config is cached).

## The four strategies

| Declaration | Behaviour | Underlying call |
| --- | --- | --- |
| `hasConfig('x')` | Publish the file under a namespaced key `<short>::x`. No merge with an existing key. | `config->set('<short>::x', ...)` |
| `mergesConfig('x')` | Shallow merge into the existing `x` key (Laravel default). | `mergeConfigFrom` |
| `overwritesConfig('x')` | Recursively replace existing values under `x`. | `array_replace_recursive` |
| `extendsConfig('x', $overwrite=false)` | Recursive, type-aware merge via `ConfigMerger`. | `ConfigMerger::merge` |

```php
$module
    ->hasConfig('catalog')          // available as config('catalog::catalog')
    ->mergesConfig('services')      // module defaults under config('services')
    ->extendsConfig('permissions'); // deep-merge a shared structure
```

## `ConfigMerger` semantics

`extendsConfig()` is the powerful one. `ConfigMerger::merge()` walks both arrays key by key and picks
a strategy **per value type** — this is deliberate and worth knowing:

- **Lists** (sequential arrays) are **unioned and de-duplicated** (`array_unique(..., SORT_REGULAR)`)
  **regardless of `$overwrite`**. Module and host list entries are always combined, never replaced —
  so two modules can each contribute middleware/permissions to the same list.
- **Associative arrays** are **merged recursively**, carrying `$overwrite` down.
- **Scalars / type mismatches**: the host value wins **unless** `$overwrite` is `true`, in which case
  the extending value replaces it.

Two further behaviours are intentional but easy to miss:

- Keys are **`ksort`ed at every level**, so the merged result is ordered by key, not by insertion
  order. Do not rely on config key ordering being preserved through an `extendsConfig` merge.
- List de-duplication uses `SORT_REGULAR`, so loosely-equal scalars collapse.

### Example

```php
// host config('permissions')        // module config/permissions.php
['roles' => ['admin'],               ['roles' => ['editor'],
 'cache' => ['ttl' => 60]]            'cache' => ['ttl' => 120, 'store' => 'redis']]

// extendsConfig('permissions')  =>
['cache' => ['store' => 'redis', 'ttl' => 60],   // assoc merged; scalar ttl kept (host wins)
 'roles' => ['admin', 'editor']]                  // list unioned; keys ksorted
```

With `extendsConfig('permissions', overwrite: true)` the scalar `ttl` would become `120`, but
`roles` would still be the union `['admin', 'editor']` — lists never respect `$overwrite`.

## Customising the merge

`ConfigMerger` implements `ConfigMergerContract`. The merge logic is intentionally fixed (it is the
documented contract above), but the interface exists so you can bind your own strategy if you have an
unusual requirement. See [builders.md](builders.md) for the config declarations and
[architecture-runtime.md](architecture-runtime.md) for why this runs in the initialize phase.
