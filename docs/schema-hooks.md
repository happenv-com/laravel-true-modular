# Lifecycle hooks & report schemas

Two "hook" surfaces exist in this package:

1. **Provider lifecycle hooks** — empty methods on `ModuleProvider` you may override to run
   custom code at a precise point in the boot sequence.
2. **Architecture report schemas** — the versioned `schemaName`/`schemaVersion` contract every
   analysis report exposes, used by the JSON renderer to emit stable machine-readable output.

## Provider lifecycle hooks

`ModuleProvider::register()`, `initialize()`, and `boot()` are `final` — you cannot override them,
because they run the feature processors in a fixed, dependency-safe order. Instead, each phase brackets
your declaration with two no-op hooks you *can* override:

| Phase | Before processors | After processors |
| --- | --- | --- |
| register | `registeringModule()` | `moduleRegistered()` |
| initialize | `initializingModule()` | `moduleInitialized()` |
| boot | `bootingModule()` | `moduleBooted()` |

```php
class CatalogServiceProvider extends ModuleProvider
{
    public function configureModule(Module $module): void { /* declarations */ }

    // Bindings belong in register(); use the hook, not register() itself.
    public function moduleRegistered(): void
    {
        $this->app->singleton(PriceCalculator::class);
    }

    // Cross-module coordination belongs in initialize().
    public function initializingModule(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
```

### Where does my code go?

The phase ordering — `register → initialize → boot` — is the heart of the package; see
[architecture-runtime.md](architecture-runtime.md) for the full rationale.

| You want to… | Hook | Why |
| --- | --- | --- |
| Bind into the container | `moduleRegistered()` | All other modules' bindings may not exist yet, but the container does. |
| Register morph maps, gates, drivers, Livewire/Filament hooks | `initializingModule()` | Runs **after every module is registered, before any boots** — every binding is available. |
| Register routes, views, commands, scheduled tasks | `bootingModule()` / `moduleBooted()` | Standard Laravel boot-time work. |

There is also `newModule()` — override it to return a `Module` subclass if you need extra builder
methods of your own.

### Initializing callbacks

For ad-hoc work without subclassing, register closures that fire around the initialize phase. They
are resolved through the container (`$this->app->call`), so you may type-hint dependencies:

```php
$this->initializing(fn (SomeService $s) => $s->prime());
$this->initialized(fn () => Log::info('catalog initialized'));
```

These map onto the application-level `initializing()` / `initialized()` callbacks fired by the custom
`Application` between the register and boot loops.

## Architecture report schemas

Every analysis report implements `ArchitectureReport`:

```php
interface ArchitectureReport
{
    public function schemaName(): string;     // 'graph' | 'impact' | 'why'
    public function schemaVersion(): int;     // currently 1 for all
    public function toArray(): array;          // the payload
}
```

The `JsonRenderer` wraps `toArray()` in a stable envelope, so consumers can branch on the schema:

```json
{
    "schema": { "name": "impact", "version": 1 },
    "module": "acme/catalog",
    "direct": ["acme/orders"],
    "indirect": ["acme/checkout"],
    "total": 2
}
```

### Current schemas (version 1)

| Schema | Payload keys |
| --- | --- |
| `graph` | `root` (nullable), `roots[]`, `dependents{ node: [dependents] }` |
| `impact` | `module`, `direct[]`, `indirect[]`, `total` |
| `why` | `from`, `to`, `path[]` (nullable when no path exists) |

### Versioning contract

`schemaVersion()` is an explicit promise: a given `(name, version)` pair always produces the same
shape. Add keys without bumping the version; **change or remove a key only by incrementing the
version**. JSON output is the integration point for CI gates and external tooling, so treat it as a
public API. When you add a new report type, give it a fresh `schemaName` rather than overloading an
existing one — see [extending-the-package.md](extending-the-package.md).
