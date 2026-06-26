# Module builders

Every module ships a service provider that extends `ModuleProvider` and implements a
single method, `configureModule(Module $module)`. Inside it you describe what the module
contains using the fluent `Module` builder. You never call `loadRoutesFrom`,
`mergeConfigFrom`, `publishes`, etc. directly — you *declare* features and the package
wires them into the correct lifecycle phase for you.

```php
namespace Acme\Catalog;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleProvider\ModuleProvider;

class CatalogServiceProvider extends ModuleProvider
{
    public function configureModule(Module $module): void
    {
        $module
            ->name('acme/catalog')
            ->hasConfig('catalog')
            ->hasRoutes('web', 'api')
            ->hasViews()
            ->hasMigrations()
            ->runsMigrations();
    }
}
```

The only required call is `->name()`. Registration throws `InvalidModule::nameIsRequired()`
if the name is missing or empty.

## How declarations map to disk

The builder records *what* the module offers; the matching `Process*` trait reads it during
the lifecycle and resolves paths relative to the **module package root** — one directory above
the provider's own folder — via `Module::vendorPath()`. A typical module looks like:

```
acme-catalog/
├── composer.json          # type: "true-module", require: { ... }
├── config/                # hasConfig(), mergesConfig(), overwritesConfig(), extendsConfig()
├── routes/                # hasRoutes(), hasBroadcastChannels(), hasSchedules()
├── database/migrations/   # hasMigrations(), discoversMigrations()
├── resources/
│   ├── views/             # hasViews()
│   ├── views-global/      # hasGlobalViews()
│   ├── lang/              # hasTranslations()
│   ├── dist/              # hasAssets()  → published to public/vendor/<short-name>
│   └── js/Pages/          # hasInertiaComponents()
└── src/
    ├── CatalogServiceProvider.php
    └── Components/         # hasViewComponents()  (lives next to the provider, not under /../)
```

> **Path note.** All resources resolve through `vendorPath()` (`<provider>/../<dir>`) *except*
> Blade view components, which are loaded from `src/Components` next to the provider. This is a
> deliberate exception — components are PHP classes, so they live with the rest of the module's code.

## Feature reference

Each row is a declaration on `Module` and the lifecycle phase that consumes it. See
[schema-hooks.md](schema-hooks.md) for the phases themselves.

### Configuration

| Method | Effect | Phase |
| --- | --- | --- |
| `hasConfig(array\|string $config)` | Publishes `config/<name>.php` under the `<short>::<name>` key. | initialize |
| `mergesConfig(array\|string $config)` | `mergeConfigFrom` — shallow merge into the existing key. | initialize |
| `overwritesConfig(array\|string $config)` | `array_replace_recursive` over the existing key. | initialize |
| `extendsConfig(array\|string $config, bool $overwrite = false)` | Recursive merge via `ConfigMerger` (lists are unioned + de-duplicated). | initialize |

Config processing is skipped entirely when the application config is cached. See
[config-merging.md](config-merging.md) for the merge semantics.

### Routing & messaging

| Method | Source | Phase |
| --- | --- | --- |
| `hasRoute(string)` / `hasRoutes(string ...)` | `routes/<name>.php` | boot |
| `hasBroadcastChannel(string = 'channels')` / `hasBroadcastChannels(string ...)` | `routes/<name>.php` (`require_once`) | boot |
| `hasSchedule(string = 'schedule')` / `hasSchedules(string ...)` | `routes/<name>.php`, console only | boot (console) |

### Database

| Method | Effect | Phase |
| --- | --- | --- |
| `hasMigration(string)` / `hasMigrations(string ...)` | Registers named migrations from `database/migrations/`. | boot |
| `discoversMigrations(bool = true, string $path = '/database/migrations')` | Auto-discovers every migration in a directory. | boot |
| `runsMigrations(bool = true)` | Loads migrations at runtime (otherwise publish-only). | boot |

### Views & front-end

| Method | Source | Phase |
| --- | --- | --- |
| `hasViews(?string $namespace = null)` | `resources/views`, namespace defaults to the short name. | boot |
| `hasGlobalViews()` | `resources/views-global`, prepended to global `view.paths`. | initialize |
| `hasViewComponent(string $prefix, string $name)` / `hasViewComponents(string $prefix, string ...$names)` | `src/Components` | boot |
| `hasViewComposer(array\|string $view, callable\|string $composer)` | View composer binding. | boot |
| `sharesDataWithAllViews(string $name, mixed $value)` | `View::share`. | boot |
| `hasInertiaComponents(?string $namespace = null)` | `resources/js/Pages` | boot |
| `hasLivewireComponents(array\|string $name, ?string $class = null)` | Livewire registration. | initialize |
| `hasTranslations()` | `resources/lang` (PHP + JSON). | boot |
| `hasAssets()` | `resources/dist` → `public/vendor/<short>`, console only. | boot |

### Console

| Method | Effect | Phase |
| --- | --- | --- |
| `hasCommand(string)` / `hasCommands(string ...)` | Registers Artisan commands. | boot |
| `hasConsoleCommand(string)` / `hasConsoleCommands(string ...)` | Console-only commands. | boot (console) |

### Domain wiring

| Method | Effect | Phase |
| --- | --- | --- |
| `hasMorphMap(array\|string $key, ?string $class = null)` | `Relation::enforceMorphMap` entries. | initialize |
| `hasPermissions(array\|string $class)` | Permission registration. | initialize |
| `hasVoters(array\|string $class)` | Authorization voters. | initialize |
| `hasEventListener($events, $listener)` | Event listener binding. | initialize |
| `hasModelExtensions(array\|string $model, ?string $extension = null)` | Adds attributes/relations to another module's model. | initialize |
| `hasModelBuilderExtensions(array\|string $builder, ?string $extension = null)` | Extends an Eloquent builder. | initialize |
| `publishesServiceProvider(string $providerName)` | Publishes a stub provider into the host app. | boot |

See [model-extensions.md](model-extensions.md) for `hasModelExtensions` / `hasModelBuilderExtensions`.

## Spread or array — both work

Every `hasXs(string ...$values)` method accepts spread arguments or a single array; both are
flattened and appended (shared `MergesFlattened` concern):

```php
$module->hasRoutes('web', 'api');
$module->hasRoutes(['web', 'api']);   // equivalent
```

## Adding your own feature

Every feature is a pair of traits kept in lockstep — a `Concerns/Package/Has*` trait that
stores the declaration on `Module`, and a `Concerns/PackageServiceProvider/Process*` trait that
consumes it. To add one you write both halves and wire the `process*()` call into the right phase.
That recipe lives in [extending-the-package.md](extending-the-package.md).
