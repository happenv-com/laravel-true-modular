## Laravel True Modular (modular monolith)

This application is a **modular monolith** built with `happenv-com/laravel-true-modular`. Treat it as
modular by default: feature code lives in **modules**, not in `app/`.

### What a module is

- A module is a Composer package under `app-modules/` whose `composer.json` has `type: "true-module"`
  and a `name` (e.g. `acme/catalog`). Dependencies between modules are ordinary `composer.json`
  `require` entries; the package boots providers in topological dependency order.
- `bootstrap/app.php` uses the package's custom `Application` (often via the fluent `ModularApplication`).
  Do not replace it with Laravel's default `Application`.

### How to build module features — declare, never hand-wire

Each module ships a service provider that **extends `ModuleProvider`** and implements
`configureModule(Module $module)`. Describe the module with the fluent `Module` builder; the package
runs each feature in the correct lifecycle phase for you. Do **not** call `loadRoutesFrom`,
`mergeConfigFrom`, `loadViewsFrom`, `publishes`, etc. directly.

@verbatim
<code-snippet name="A module service provider" lang="php">
class CatalogServiceProvider extends ModuleProvider
{
    public function configureModule(Module $module): void
    {
        $module
            ->name('acme/catalog')      // required
            ->hasConfig('catalog')
            ->hasRoutes('web', 'api')
            ->hasViews()
            ->hasMigrations()
            ->runsMigrations();
    }
}
</code-snippet>
@endverbatim

### Lifecycle: register → initialize → boot

There is an extra `initialize()` phase between `register()` and `boot()`. Place code by phase, using the
overridable hooks (the phases themselves are `final`):

- `moduleRegistered()` — container bindings only; do not resolve other modules yet.
- `initializingModule()` — cross-module wiring that needs every module registered but must precede boot:
  morph maps, gates/permissions, driver registration, model extensions, Livewire/Filament hooks.
- `bootingModule()` — routes, views, commands, schedules (the normal boot-time work).

### Cross-module model changes

To add an attribute or relation to **another module's** Eloquent model, declare a model extension in the
downstream module (`->hasModelExtensions(Model::class, Extension::class)`), keeping the dependency arrow
pointing from the dependent module to the one that owns the model. Never make a low-level module require
a high-level one, and never create dependency cycles.

### Inspect the architecture

Use `php artisan module:list`, `module:graph`, `module:impact <module>`, and `module:why <from> <to>`
(all support `--format=json`) to understand module ordering and dependencies before changing code.

For detailed patterns, activate the **modular-monolith-development** skill, and consult the package docs
under `vendor/happenv-com/laravel-true-modular/docs/`.
