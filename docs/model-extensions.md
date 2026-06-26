# Model extensions

Model extensions let one module add **attributes** and **relations** to an Eloquent model that
*belongs to another module* — without editing that model's class. This is how a downstream module
(say `billing`) augments an upstream model (say `catalog`'s `Product`) while keeping the dependency
arrow pointing the right way (`billing` → `catalog`).

## Declaring an extension

In the augmenting module's provider, declare which extension class augments which model:

```php
public function configureModule(Module $module): void
{
    $module
        ->name('acme/billing')
        ->hasModelExtensions(Product::class, ProductBillingExtension::class);

    // or many at once:
    $module->hasModelExtensions([
        Product::class => ProductBillingExtension::class,
        Order::class   => OrderBillingExtension::class,
    ]);
}
```

Both the model and extension class names are validated at declaration time — a non-existent class
throws `InvalidArgumentException`.

## Writing the extension class

Extend `ModelExtension`; the target model instance is injected as `$this->model`:

```php
use Happenv\LaravelTrueModular\ModelExtension\ModelExtension;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductBillingExtension extends ModelExtension
{
    // Attribute: a get{Studly}Attribute method, like a classic Eloquent accessor.
    public function getFormattedPriceAttribute(): string
    {
        return Money::format($this->model->price);
    }

    // Relation: any public method whose return type is an Eloquent Relation.
    public function invoices(): HasMany
    {
        return $this->model->hasMany(Invoice::class);
    }
}
```

`$product->formatted_price` and `$product->invoices` now work as if defined on `Product` itself.

## How it resolves (the `initialize` phase)

`processModelExtensions()` runs in the **initialize** phase — after every module is registered but
before any boots — so extensions are wired before the models are used. For each declared pair it
registers two mechanisms:

- **Attributes** — `AttributeResolver::register()` installs a missing-attribute handler on the model
  the first time it is extended. When an undeclared attribute is read, each registered extension is
  asked for a `get{Studly}Attribute` method; the first that has one wins. A unique sentinel object
  distinguishes "resolved to `null`" from "not provided" — comparing against `null` would conflate
  the two. The registry (`AttributeResolversBag`) is static because Eloquent's hook has no container
  access.

- **Relations** — `DynamicRelations::register()` reflects over the extension's public methods and,
  for each one whose return type is an Eloquent `Relation`, calls `Model::resolveRelationUsing()`.

There is also `hasModelBuilderExtensions()` (and `processModelBuilderExtensions()`), which extends an
Eloquent **builder/query** rather than a model — same lifecycle phase, declared the same way.

## Why this is the right tool

Putting the extension in the downstream module keeps the dependency direction correct: the module
that *knows about* the new behaviour depends on the one that owns the model, never the reverse. See
[best-practices.md](best-practices.md) and [anti-patterns.md](anti-patterns.md) for the dependency-
direction rules this preserves.
