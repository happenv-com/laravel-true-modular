# Model Extensions

Model extensions allow modules to add relations and attributes to existing Eloquent models without modifying the original class.

## Registration

```php
$this->module->hasModelExtensions([
    User::class => UserExtension::class,
]);
```

Multiple modules can register extensions for the same model. All extensions are applied — there is no overwriting.

## Writing an Extension

An extension must extend `Happenv\LaravelTrueModular\ModelExtension\ModelExtension`. The base class provides `$this->model` with the model instance injected via the constructor.

```php
use Happenv\LaravelTrueModular\ModelExtension\ModelExtension;

class UserExtension extends ModelExtension
{
}
```

---

## Relations

Define a public method with a return type that extends `Illuminate\Database\Eloquent\Relations\Relation`. The method body uses `$this->model` exactly as you would use `$this` inside an Eloquent model.

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function orders(): HasMany
{
    return $this->model->hasMany(Order::class);
}

public function allegroCredentialsChannels(): HasMany
{
    return $this->model->hasMany(AllegroCredentialsChannel::class, 'channel_id');
}
```

The return type is required — it is used to detect relation methods. Methods without a `Relation` return type are ignored.

---

## Attributes

Only old-style accessors are currently supported. The method must follow the `get{Name}Attribute` naming convention.

```php
public function getFullNameAttribute(): string
{
    return $this->model->first_name . ' ' . $this->model->last_name;
}
```

Accessing `$user->full_name` will call `getFullNameAttribute()` on the first extension that defines it.

#### Not supported

- New-style accessors returning `Illuminate\Database\Eloquent\Casts\Attribute`
- Mutators / setters

---

## Internals

| Class | Responsibility |
|---|---|
| `AttributeResolver` | Registers a single `handleMissingAttributeViolationUsing` handler per model that delegates to the bag |
| `AttributeResolversBag` | Static registry of `ExtensionAttributeResolver` instances per model, tried in registration order |
| `ExtensionAttributeResolver` | Checks a single extension for the accessor method and returns the value or a sentinel |
| `DynamicRelations` | Uses reflection to find methods with a `Relation` return type and registers them via `resolveRelationUsing` |
