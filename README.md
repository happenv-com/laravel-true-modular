# Laravel True Modular


Extended Laravel Application with topological service provider ordering and enhanced lifecycle.

## Overview

The Kernel module provides a custom `Application` class that extends Laravel's foundation application with two key enhancements:

1. **Topological Provider Ordering** - Service providers from app modules are automatically sorted based on their `composer.json` dependencies
2. **Enhanced Lifecycle** - A new `initialize()` method between `register()` and `boot()`

## Application Lifecycle

### Laravel Default

```mermaid
flowchart LR
    A[register] --> B[boot]
```

### True Modular Extended

```mermaid
flowchart LR
    A[register] --> B[initialize] --> C[boot]
    style B fill:#22c55e,color:#fff
```

### Lifecycle Phases

| Phase          | Purpose                          | Use Cases                                |
| -------------- | -------------------------------- | ---------------------------------------- |
| `register()`   | Bind services to container       | Singletons, bindings, interfaces         |
| `initialize()` | Configure cross-cutting concerns | Morph maps, permissions, hooks, drivers  |
| `boot()`       | Bootstrap services               | Routes, views, commands, event listeners |

### Why `initialize()`?

In a modular monolith, modules often need to register things that depend on other modules being registered first, but before the full boot phase:

- **Morph Maps** - `Relation::morphMap()` needs all models registered
- **Filament Hooks** - Schema hooks need Filament resources registered
- **Permissions** - Permission registration needs auth models ready
- **Drivers** - Custom drivers for other modules' managers

The `initialize()` phase runs **after all providers are registered** and **before any provider boots**, ensuring cross-module dependencies are satisfied.

## Usage

### Implementing `initialize()` in a Service Provider

```php
<?php

namespace Myapp\Sale;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class SaleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind services to container
        $this->app->singleton(OrderService::class);
    }

    public function initialize(): void
    {
        // Register morph maps (all models are now available)
        Relation::morphMap([
            'order' => Order::class,
            'payment' => Payment::class,
        ]);

        // Register permissions
        app(PermissionRegistrar::class)->register([
            'orders.view',
            'orders.create',
        ]);

        // Register Filament hooks
        ProductResource::registerSchemaHook('sale', fn () => [
            TextColumn::make('orders_count'),
        ]);
    }

    public function boot(): void
    {
        // Load routes, views, commands
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
}
```

### Execution Order

Providers are executed in **topological order** based on module dependencies:

```
myapp/kernel      (no dependencies)
myapp/core        (depends on kernel)
myapp/pim         (depends on core)
myapp/inventory   (depends on core)
myapp/sale        (depends on core, pim, inventory)
myapp/amazon      (depends on sale, pim)
```

This ensures that when `SaleServiceProvider::initialize()` runs, all its dependencies (`core`, `pim`, `inventory`) have already completed their `initialize()` phase.

## Architecture

### ServiceProviderSorter

Sorts service providers according to module dependency order:

```php
use Myapp\Kernel\ServiceProviderSorter;

$sorter = app(ServiceProviderSorter::class);

// Sort providers - non-modules first, then modules in topological order
$sorted = $sorter->sort($providers);

// Get module name for a provider
$module = $sorter->getModuleName($provider);
// Returns: 'myapp/sale' or null

// Group providers by module
$grouped = $sorter->groupByModule($providers);
// Returns: ['myapp/core' => [...], 'myapp/sale' => [...]]
```

### How Sorting Works

1. **Separate** providers into modules vs others (Laravel, Spatie, etc.)
2. **Preserve** original order for non-modules providers
3. **Sort** Module providers using Kahn's topological sort algorithm
4. **Merge** - other providers first, then sorted Module providers

```
Input:  [Laravel, Filament, Sale, Core, Spatie, Amazon, Pim]
Output: [Laravel, Filament, Spatie, Core, Pim, Sale, Amazon]
                                    ↑─── Module sorted ───↑
```

## Configuration

The custom Application is configured in `bootstrap/app.php`:

```php
<?php

use Myapp\Kernel\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(...)
    ->withMiddleware(...)
    ->create();
```

## Best Practices

### DO use `initialize()` for:

- `Relation::morphMap()` registration
- `Gate::define()` and permission registration
- Filament schema hooks and customizations
- Driver registration for managers
- Cross-module event listener registration

### DON'T use `initialize()` for:

- Container bindings → use `register()`
- Loading routes/views/commands → use `boot()`
- Anything that doesn't need cross-module coordination

### Migration from existing code

If you have code in `boot()` that registers morph maps, permissions, or hooks, move it to `initialize()`:

```php
// Before (in boot)
public function boot(): void
{
    Relation::morphMap(['order' => Order::class]); // ❌ Too late
    $this->loadRoutesFrom(...);
}

// After (split between initialize and boot)
public function initialize(): void
{
    Relation::morphMap(['order' => Order::class]); // ✅ Perfect timing
}

public function boot(): void
{
    $this->loadRoutesFrom(...);
}
```

## Config Extension (`extendConfigs`)

Modules can extend existing config files using `configsToExtend`. The merge follows these rules:

| Original value | Extending value | Result                                                              |
| -------------- | --------------- | ------------------------------------------------------------------- |
| scalar         | scalar          | keeps original (`overwrite: false`) or replaces (`overwrite: true`) |
| list `[a, b]`  | list `[b, c]`   | merged + deduplicated → `[a, b, c]`                                 |
| assoc array    | assoc array     | recursively merged with the same rules                              |
| new key        | any             | always added                                                        |

Lists (`array_is_list`) are always extended regardless of the `overwrite` flag. Scalars and type mismatches (e.g. scalar vs array) respect `overwrite`.
