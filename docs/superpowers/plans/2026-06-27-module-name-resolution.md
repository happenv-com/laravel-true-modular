# Module Name Resolution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let every module-name-taking command accept a bare (vendor-less) name — `module:impact amazon` instead of `module:impact happenv/amazon` — while full `vendor/name` still works for external packages.

**Architecture:** A bare name (no `/`) is lowercased and qualified with a default vendor derived from the configured modules namespace; a name containing `/` is lowercased and passed through. Resolution lives in one place, `ModuleLocator::resolve()` / `resolveOrFail()`, which every command calls. The architecture index and module registry stay exact-match and pure.

**Tech Stack:** PHP 8.3+, Laravel 12/13, Pest 4, orchestra/testbench, `thecodingmachine/safe`.

## Global Constraints

- PHP 8.3+, Laravel 12/13. Use `Safe\` functions for filesystem/json/pcre where the codebase already does.
- `declare(strict_types=1);` at the top of every PHP file.
- Default modules namespace is `TrueModule` (`Application::DEFAULT_MODULES_NAMESPACE`), so the default vendor is `true-module`.
- Test fixtures live under `tests/fixtures/app-modules/` and use vendor `myapp` (`myapp/amazon`, `myapp/core`, `myapp/kernel`, `myapp/pim`, `myapp/sale`). `myapp/core` requires `myapp/kernel`.
- Run a single test file with `vendor/bin/pest <path>`; format with `vendor/bin/pint`; static analysis with `vendor/bin/phpstan analyse` (level 6 + larastan).
- `ModuleDescriptor` is the single source of truth for a module's `path`, `namespace`, `provider`, and full package `name`. Never re-assemble those from strings.

---

## File Structure

- `src/ModuleSystem/ModuleName.php` — **new.** Pure helper: derive default vendor from a namespace; qualify a typed name (lowercase + slash rule).
- `src/Application.php` — **modify.** Add static `getModulesVendor()`.
- `src/Generators/ModuleGenerator.php` — **modify.** Use `ModuleName::vendorFromNamespace()` instead of an inline duplicate.
- `src/Architecture/Module/ModuleLocator.php` — **modify.** Add `resolve()` + `resolveOrFail()` to the interface.
- `src/Architecture/Module/AppModulesLocator.php` — **modify.** Add a `defaultVendor` constructor arg; implement `resolve()` / `resolveOrFail()`.
- `src/KernelServiceProvider.php` — **modify.** Pass `Application::getModulesVendor()` into the locator binding.
- `src/Commands/ModuleImpactCommand.php`, `ModuleWhyCommand.php`, `ModuleGraphCommand.php` — **modify.** Inject `ModuleLocator`, resolve arguments.
- `src/Commands/SeedModulesCommand.php` — **modify.** Inject `ModuleLocator`, resolve `--module`.
- `src/Commands/MakeMigrationCommand.php` — **modify.** Inject `ModuleLocator`, resolve `module`, use `ModuleDescriptor::$path`.
- `README.md` — **modify.** Add an external-package note next to the bare-name examples.
- Tests: `tests/Unit/ModuleNameTest.php` (new), `tests/Unit/ApplicationVendorTest.php` (new), and additions to `tests/Unit/Architecture/AppModulesLocatorTest.php`, `tests/Feature/ModuleImpactCommandTest.php`, `ModuleWhyCommandTest.php`, `ModuleGraphCommandTest.php`, `SeedModulesCommandTest.php`, plus new `tests/Feature/MakeMigrationCommandTest.php`. Update the two existing `new AppModulesLocator(...)` call sites.

---

## Task 1: `ModuleName` pure helper

**Files:**
- Create: `src/ModuleSystem/ModuleName.php`
- Test: `tests/Unit/ModuleNameTest.php`

**Interfaces:**
- Produces:
  - `ModuleName::vendorFromNamespace(string $namespace): string`
  - `ModuleName::qualify(string $name, string $vendor): string`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ModuleNameTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleName;

it('derives a kebab vendor from a namespace', function (): void {
    expect(ModuleName::vendorFromNamespace('TrueModule'))->toBe('true-module')
        ->and(ModuleName::vendorFromNamespace('Happenv'))->toBe('happenv')
        ->and(ModuleName::vendorFromNamespace('Acme\\Modules'))->toBe('modules');
});

it('qualifies a bare name with the vendor and lowercases it', function (): void {
    expect(ModuleName::qualify('inventory', 'happenv'))->toBe('happenv/inventory')
        ->and(ModuleName::qualify('Inventory', 'happenv'))->toBe('happenv/inventory')
        ->and(ModuleName::qualify('INVENTORY', 'happenv'))->toBe('happenv/inventory');
});

it('passes through a slashed name unchanged except for lowercasing', function (): void {
    expect(ModuleName::qualify('acme/catalog', 'happenv'))->toBe('acme/catalog')
        ->and(ModuleName::qualify('Acme/Catalog', 'happenv'))->toBe('acme/catalog')
        ->and(ModuleName::qualify('filament/actions', 'happenv'))->toBe('filament/actions');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ModuleNameTest.php`
Expected: FAIL — class `Happenv\LaravelTrueModular\ModuleSystem\ModuleName` not found.

- [ ] **Step 3: Write minimal implementation**

Create `src/ModuleSystem/ModuleName.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Illuminate\Support\Str;

/**
 * Pure module-name helpers: derive the default vendor from a namespace and
 * qualify a typed name. The single home for the "no slash → prepend vendor"
 * rule and Composer-style lowercasing.
 */
final class ModuleName
{
    public static function vendorFromNamespace(string $namespace): string
    {
        return Str::kebab(class_basename(str_replace('\\', '/', $namespace)));
    }

    public static function qualify(string $name, string $vendor): string
    {
        $name = Str::lower($name);

        return str_contains($name, '/') ? $name : $vendor.'/'.$name;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ModuleNameTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Format, analyse, commit**

```bash
vendor/bin/pint src/ModuleSystem/ModuleName.php tests/Unit/ModuleNameTest.php
vendor/bin/phpstan analyse
git add src/ModuleSystem/ModuleName.php tests/Unit/ModuleNameTest.php
git commit -m "feat: add ModuleName helper for vendor derivation and name qualification"
```

---

## Task 2: `Application::getModulesVendor()` + generator de-duplication

**Files:**
- Modify: `src/Application.php` (add method near `getModulesNamespace()`, ~line 260)
- Modify: `src/Generators/ModuleGenerator.php:44`
- Test: `tests/Unit/ApplicationVendorTest.php`

**Interfaces:**
- Consumes: `ModuleName::vendorFromNamespace()` (Task 1).
- Produces: `Application::getModulesVendor(): string` (static).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ApplicationVendorTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Application;

afterEach(function (): void {
    Application::modulesNamespace(Application::DEFAULT_MODULES_NAMESPACE);
});

it('derives the default vendor from the default namespace', function (): void {
    expect(Application::getModulesVendor())->toBe('true-module');
});

it('derives the vendor from a custom namespace', function (): void {
    Application::modulesNamespace('Happenv');

    expect(Application::getModulesVendor())->toBe('happenv');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ApplicationVendorTest.php`
Expected: FAIL — `Call to undefined method ...Application::getModulesVendor()`.

- [ ] **Step 3: Add the method**

In `src/Application.php`, add the `ModuleName` import with the other `use` statements:

```php
use Happenv\LaravelTrueModular\ModuleSystem\ModuleName;
```

Immediately after `getModulesNamespace()` (around line 262), add:

```php
    /**
     * The default Composer vendor for local modules, derived from the modules
     * namespace (e.g. `Happenv` → `happenv`). Used to qualify bare module names.
     */
    public static function getModulesVendor(): string
    {
        return ModuleName::vendorFromNamespace(self::getModulesNamespace());
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ApplicationVendorTest.php`
Expected: PASS (2 passed).

- [ ] **Step 5: De-duplicate the generator**

In `src/Generators/ModuleGenerator.php`, add the import:

```php
use Happenv\LaravelTrueModular\ModuleSystem\ModuleName;
```

Replace line 44:

```php
        $vendor = Str::kebab(class_basename(str_replace('\\', '/', $namespace)));
```

with:

```php
        $vendor = ModuleName::vendorFromNamespace($namespace);
```

- [ ] **Step 6: Run the generator's existing tests to verify no regression**

Run: `vendor/bin/pest tests/Feature/ --filter=make` (covers `module:make` scaffolding which asserts the generated package vendor)
Expected: PASS. If no `make` feature test exists, run the full suite: `vendor/bin/pest`.

- [ ] **Step 7: Format, analyse, commit**

```bash
vendor/bin/pint src/Application.php src/Generators/ModuleGenerator.php tests/Unit/ApplicationVendorTest.php
vendor/bin/phpstan analyse
git add src/Application.php src/Generators/ModuleGenerator.php tests/Unit/ApplicationVendorTest.php
git commit -m "feat: add Application::getModulesVendor() and reuse it in the generator"
```

---

## Task 3: `ModuleLocator::resolve()` / `resolveOrFail()`

**Files:**
- Modify: `src/Architecture/Module/ModuleLocator.php`
- Modify: `src/Architecture/Module/AppModulesLocator.php`
- Modify: `src/KernelServiceProvider.php:68-71`
- Modify (call-site fixups): `tests/Unit/Architecture/AppModulesLocatorTest.php:11`, `tests/Unit/Architecture/ComposerArchitectureSourceTest.php:15`
- Test: additions to `tests/Unit/Architecture/AppModulesLocatorTest.php`

**Interfaces:**
- Consumes: `ModuleName::qualify()` (Task 1), `Application::getModulesVendor()` (Task 2), existing `byComposerPackage(string): ?ModuleDescriptor`.
- Produces:
  - `ModuleLocator::resolve(string $name): ?ModuleDescriptor`
  - `ModuleLocator::resolveOrFail(string $name): ModuleDescriptor` (throws `\InvalidArgumentException`)
  - `AppModulesLocator::__construct(ModuleRegistry $moduleRegistry, string $defaultVendor, string $coreName = 'core')`

- [ ] **Step 1: Fix the two existing locator constructions (they gain a required arg)**

In `tests/Unit/Architecture/AppModulesLocatorTest.php`, change `locatorFixture()`:

```php
function locatorFixture(): AppModulesLocator
{
    return new AppModulesLocator(new ModuleRegistry(appModulesFixture()), 'myapp');
}
```

In `tests/Unit/Architecture/ComposerArchitectureSourceTest.php:15`, change:

```php
    return new ComposerArchitectureSource($tree, new AppModulesLocator($tree, 'myapp'));
```

(These call sites pass the fixture vendor `myapp`; the changes keep them compiling once the constructor below requires `defaultVendor`.)

- [ ] **Step 2: Write the failing tests**

Append to `tests/Unit/Architecture/AppModulesLocatorTest.php`:

```php
it('resolves a bare name by prepending the default vendor', function (): void {
    expect(locatorFixture()->resolve('sale')?->name)->toBe('myapp/sale')
        ->and(locatorFixture()->resolve('amazon')?->name)->toBe('myapp/amazon');
});

it('resolves case-insensitively', function (): void {
    expect(locatorFixture()->resolve('Sale')?->name)->toBe('myapp/sale')
        ->and(locatorFixture()->resolve('SALE')?->name)->toBe('myapp/sale');
});

it('passes a slashed name through resolution unchanged', function (): void {
    expect(locatorFixture()->resolve('myapp/sale')?->name)->toBe('myapp/sale')
        ->and(locatorFixture()->resolve('vendor/nope'))->toBeNull();
});

it('returns null from resolve for an unknown bare name', function (): void {
    expect(locatorFixture()->resolve('nope'))->toBeNull();
});

it('resolveOrFail returns the descriptor for a known module', function (): void {
    expect(locatorFixture()->resolveOrFail('sale')->name)->toBe('myapp/sale');
});

it('resolveOrFail throws a friendly error showing the resolved name', function (): void {
    locatorFixture()->resolveOrFail('amazonx');
})->throws(
    InvalidArgumentException::class,
    "Unknown module: amazonx\nResolved to: myapp/amazonx",
);

it('resolveOrFail omits the resolved line when the input has a slash', function (): void {
    locatorFixture()->resolveOrFail('vendor/nope');
})->throws(InvalidArgumentException::class, 'Unknown module: vendor/nope');
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Architecture/AppModulesLocatorTest.php`
Expected: FAIL — `resolve()` / `resolveOrFail()` not defined.

- [ ] **Step 4: Extend the interface**

In `src/Architecture/Module/ModuleLocator.php`, add to the interface:

```php
    public function resolve(string $name): ?ModuleDescriptor;

    public function resolveOrFail(string $name): ModuleDescriptor;
```

- [ ] **Step 5: Implement in `AppModulesLocator`**

Add the import:

```php
use Happenv\LaravelTrueModular\ModuleSystem\ModuleName;
use InvalidArgumentException;
```

Change the constructor to require `defaultVendor`:

```php
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly string $defaultVendor,
        private readonly string $coreName = 'core',
    ) {}
```

Add the two methods (place them just above `byComposerPackage()`):

```php
    public function resolve(string $name): ?ModuleDescriptor
    {
        return $this->byComposerPackage(ModuleName::qualify($name, $this->defaultVendor));
    }

    public function resolveOrFail(string $name): ModuleDescriptor
    {
        $descriptor = $this->resolve($name);

        if ($descriptor !== null) {
            return $descriptor;
        }

        $lines = ['Unknown module: '.$name];

        if (! str_contains($name, '/')) {
            $lines[] = 'Resolved to: '.ModuleName::qualify($name, $this->defaultVendor);
        }

        $lines[] = 'Run `php artisan module:list` to see available modules.';

        throw new InvalidArgumentException(implode("\n", $lines));
    }
```

- [ ] **Step 6: Update the container binding**

In `src/KernelServiceProvider.php`, change the `ModuleLocator` binding (lines 68-71) to pass the default vendor:

```php
        $this->app->singleton(
            ModuleLocator::class,
            static fn (Application $app): AppModulesLocator => new AppModulesLocator(
                $app->make(ModuleRegistry::class),
                Application::getModulesVendor(),
            ),
        );
```

(`Application` is already imported in this file as `Happenv\LaravelTrueModular\Application`.)

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Architecture/AppModulesLocatorTest.php tests/Unit/Architecture/ComposerArchitectureSourceTest.php`
Expected: PASS (all locator tests, including the 7 new ones).

- [ ] **Step 8: Format, analyse, commit**

```bash
vendor/bin/pint src/Architecture/Module/ModuleLocator.php src/Architecture/Module/AppModulesLocator.php src/KernelServiceProvider.php tests/Unit/Architecture/
vendor/bin/phpstan analyse
git add src/Architecture/Module/ModuleLocator.php src/Architecture/Module/AppModulesLocator.php src/KernelServiceProvider.php tests/Unit/Architecture/
git commit -m "feat: add ModuleLocator::resolve()/resolveOrFail() for vendor-less names"
```

---

## Task 4: Wire the architecture commands (impact / why / graph)

**Files:**
- Modify: `src/Commands/ModuleImpactCommand.php`, `ModuleWhyCommand.php`, `ModuleGraphCommand.php`
- Modify: `tests/Feature/ModuleImpactCommandTest.php`, `ModuleWhyCommandTest.php`, `ModuleGraphCommandTest.php`

**Interfaces:**
- Consumes: `ModuleLocator::resolveOrFail()` (Task 3), existing `ModuleDescriptor::$name`.
- Note: `AbstractArchitectureCommand::handle()` already catches `\InvalidArgumentException` from `buildReport()` and prints it, so `resolveOrFail()` errors render cleanly with no extra handling.

- [ ] **Step 1: Write failing feature tests (bare names)**

Add to `tests/Feature/ModuleImpactCommandTest.php` a helper that registers the command with a `myapp`-vendor locator, plus bare-name cases:

```php
use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

function registerImpactWithVendor(string $vendor): void
{
    Artisan::registerCommand(new ModuleImpactCommand(
        app(ArchitectureIndexBuilder::class),
        app(ImpactAnalyzer::class),
        app(RendererRegistry::class),
        new AppModulesLocator(app(ModuleRegistry::class), $vendor),
    ));
}

it('accepts a bare module name', function (): void {
    registerImpactWithVendor('myapp');

    $this->artisan('module:impact', ['module' => 'kernel'])
        ->assertSuccessful()
        ->expectsOutputToContain('Total affected:');
});

it('fails with a friendly error for an unknown bare name', function (): void {
    registerImpactWithVendor('myapp');

    $this->artisan('module:impact', ['module' => 'nope'])
        ->assertFailed()
        ->expectsOutputToContain('Unknown module: nope')
        ->expectsOutputToContain('Resolved to: myapp/nope');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/ModuleImpactCommandTest.php`
Expected: FAIL — `ModuleImpactCommand::__construct()` does not accept a 4th argument yet.

- [ ] **Step 3: Add the locator to the three command constructors and resolve arguments**

`src/Commands/ModuleImpactCommand.php` — add import and constructor arg, resolve in `buildReport`:

```php
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
```

```php
    public function __construct(
        ArchitectureIndexBuilder $builder,
        private readonly ImpactAnalyzer $analyzer,
        RendererRegistry $renderers,
        private readonly ModuleLocator $locator,
    ) {
        parent::__construct($builder, $renderers);
    }

    #[Override]
    protected function buildReport(ArchitectureIndex $index): ArchitectureReport
    {
        $module = $this->locator->resolveOrFail((string) $this->argument('module'))->name;

        return $this->analyzer->analyze($index, $module);
    }
```

`src/Commands/ModuleWhyCommand.php` — same import + constructor arg, resolve both arguments:

```php
    public function __construct(
        ArchitectureIndexBuilder $builder,
        private readonly WhyAnalyzer $analyzer,
        RendererRegistry $renderers,
        private readonly ModuleLocator $locator,
    ) {
        parent::__construct($builder, $renderers);
    }

    #[Override]
    protected function buildReport(ArchitectureIndex $index): ArchitectureReport
    {
        return $this->analyzer->analyze(
            $index,
            $this->locator->resolveOrFail((string) $this->argument('from'))->name,
            $this->locator->resolveOrFail((string) $this->argument('to'))->name,
        );
    }
```

`src/Commands/ModuleGraphCommand.php` — same import + constructor arg, resolve `--root` only when present:

```php
    public function __construct(
        ArchitectureIndexBuilder $builder,
        private readonly GraphAnalyzer $analyzer,
        RendererRegistry $renderers,
        private readonly ModuleLocator $locator,
    ) {
        parent::__construct($builder, $renderers);
    }

    #[Override]
    protected function buildReport(ArchitectureIndex $index): ArchitectureReport
    {
        $root = $this->option('root');

        if ($root !== null) {
            $root = $this->locator->resolveOrFail((string) $root)->name;
        }

        return $this->analyzer->analyze($index, $root);
    }
```

- [ ] **Step 4: Update the existing `beforeEach` registrations in all three feature tests**

Each test's `beforeEach` constructs the command directly; add the locator as the 4th argument so the existing full-name tests keep compiling. For `ModuleImpactCommandTest.php`:

```php
beforeEach(function (): void {
    Artisan::registerCommand(new ModuleImpactCommand(
        app(ArchitectureIndexBuilder::class),
        app(ImpactAnalyzer::class),
        app(RendererRegistry::class),
        app(\Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator::class),
    ));
});
```

Apply the equivalent 4th argument (`app(ModuleLocator::class)`) to the `beforeEach` in `ModuleWhyCommandTest.php` and `ModuleGraphCommandTest.php`.

- [ ] **Step 5: Run the three feature tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/ModuleImpactCommandTest.php tests/Feature/ModuleWhyCommandTest.php tests/Feature/ModuleGraphCommandTest.php`
Expected: PASS — existing full-name cases still work (slashed names pass through), and the new bare-name cases pass.

- [ ] **Step 6: Format, analyse, commit**

```bash
vendor/bin/pint src/Commands/ tests/Feature/
vendor/bin/phpstan analyse
git add src/Commands/ModuleImpactCommand.php src/Commands/ModuleWhyCommand.php src/Commands/ModuleGraphCommand.php tests/Feature/ModuleImpactCommandTest.php tests/Feature/ModuleWhyCommandTest.php tests/Feature/ModuleGraphCommandTest.php
git commit -m "feat: resolve vendor-less names in module:impact/why/graph"
```

---

## Task 5: Wire `module:seed`

**Files:**
- Modify: `src/Commands/SeedModulesCommand.php`
- Modify: `tests/Feature/SeedModulesCommandTest.php`

**Interfaces:**
- Consumes: `ModuleLocator::resolveOrFail()` (Task 3).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/SeedModulesCommandTest.php`. (The command is registered through the package provider; rebind the locator to the fixture vendor so bare names resolve.)

```php
use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

function bindLocatorVendor(string $vendor): void
{
    app()->singleton(
        ModuleLocator::class,
        static fn ($app): AppModulesLocator => new AppModulesLocator($app->make(ModuleRegistry::class), $vendor),
    );
}

it('accepts a bare module name for --module', function (): void {
    bindLocatorVendor('myapp');

    $this->artisan('module:seed', ['--module' => 'core'])
        ->assertSuccessful();
});

it('fails with a friendly error for an unknown --module', function (): void {
    bindLocatorVendor('myapp');

    $this->artisan('module:seed', ['--module' => 'nope'])
        ->assertFailed()
        ->expectsOutputToContain('Unknown module: nope');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/SeedModulesCommandTest.php`
Expected: FAIL — bare `core` currently resolves to nothing (silent "No seeders found", success) and the unknown case does not emit the friendly error.

- [ ] **Step 3: Inject the locator and resolve `--module`**

In `src/Commands/SeedModulesCommand.php`, add imports:

```php
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use InvalidArgumentException;
```

Add the locator to the constructor:

```php
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ModuleFileFinder $fileFinder,
        private readonly ModuleLocator $locator,
    ) {
        parent::__construct();
    }
```

In `handle()`, replace the `--module` block:

```php
        $specificModule = $this->option('module');
        $specificClass = $this->option('class');

        if ($specificModule !== null) {
            return $this->seedModule($specificModule, $specificClass);
        }
```

with a resolving + guarded version:

```php
        $specificModule = $this->option('module');
        $specificClass = $this->option('class');

        if ($specificModule !== null) {
            try {
                $specificModule = $this->locator->resolveOrFail($specificModule)->name;
            } catch (InvalidArgumentException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            return $this->seedModule($specificModule, $specificClass);
        }
```

- [ ] **Step 4: Run to verify the tests pass**

Run: `vendor/bin/pest tests/Feature/SeedModulesCommandTest.php`
Expected: PASS (existing cases plus the two new ones).

- [ ] **Step 5: Format, analyse, commit**

```bash
vendor/bin/pint src/Commands/SeedModulesCommand.php tests/Feature/SeedModulesCommandTest.php
vendor/bin/phpstan analyse
git add src/Commands/SeedModulesCommand.php tests/Feature/SeedModulesCommandTest.php
git commit -m "feat: resolve vendor-less name in module:seed --module"
```

---

## Task 6: Wire `module:make:migration` (use `ModuleDescriptor::$path`)

**Files:**
- Modify: `src/Commands/MakeMigrationCommand.php`
- Test: `tests/Feature/MakeMigrationCommandTest.php` (new)

**Interfaces:**
- Consumes: `ModuleLocator::resolveOrFail()` (Task 3), `ModuleDescriptor::$path`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/MakeMigrationCommandTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use function Safe\glob;

beforeEach(function (): void {
    app()->singleton(
        ModuleLocator::class,
        static fn ($app): AppModulesLocator => new AppModulesLocator($app->make(ModuleRegistry::class), 'myapp'),
    );
});

it('creates a migration in the resolved module path for a bare name', function (): void {
    $dir = appModulesFixture().'/sale/database/migrations';

    $this->artisan('module:make:migration', ['module' => 'sale', 'name' => 'create_widgets_table'])
        ->assertSuccessful();

    $created = glob($dir.'/*_create_widgets_table.php');

    expect($created)->not->toBeEmpty();

    foreach ($created as $file) {
        unlink($file);
    }
});

it('fails with a friendly error for an unknown module', function (): void {
    $this->artisan('module:make:migration', ['module' => 'nope', 'name' => 'create_x_table'])
        ->assertFailed()
        ->expectsOutputToContain('Unknown module: nope');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/MakeMigrationCommandTest.php`
Expected: FAIL — the command does not inject a locator and builds the path from the raw argument via `module_path()`.

- [ ] **Step 3: Inject the locator and use the descriptor path**

Replace the body of `src/Commands/MakeMigrationCommand.php` with:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use InvalidArgumentException;

class MakeMigrationCommand extends Command
{
    protected $signature = 'module:make:migration {module : The name of the module} {name : The name of the migration} {--create= : The table to be created} {--table= : The table to migrate}';

    protected $description = 'Create a new migration for a module';

    public function __construct(private readonly ModuleLocator $locator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $module = $this->locator->resolveOrFail((string) $this->argument('module'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $path = $module->path.'/database/migrations';

        $this->runCommand(MigrateMakeCommand::class, [
            '--create' => $this->option('create'),
            '--path' => $path,
            '--realpath' => $path,
            '--table' => $this->option('table'),
            'name' => $this->argument('name'),
        ], $this->output);

        return self::SUCCESS;
    }
}
```

(Note: the `module_path` import and `getMigrationPath()` helper are removed; the path now comes solely from `ModuleDescriptor::$path`.)

- [ ] **Step 4: Run to verify the tests pass**

Run: `vendor/bin/pest tests/Feature/MakeMigrationCommandTest.php`
Expected: PASS (2 passed). The happy-path test cleans up the file it creates.

- [ ] **Step 5: Format, analyse, commit**

```bash
vendor/bin/pint src/Commands/MakeMigrationCommand.php tests/Feature/MakeMigrationCommandTest.php
vendor/bin/phpstan analyse
git add src/Commands/MakeMigrationCommand.php tests/Feature/MakeMigrationCommandTest.php
git commit -m "feat: resolve module via descriptor path in module:make:migration"
```

---

## Task 7: README — clarify bare vs. full names

**Files:**
- Modify: `README.md` (the "The module graph" command block and the surrounding prose)

**Interfaces:** none (docs only).

- [ ] **Step 1: Add an external-package note under the graph command examples**

In `README.md`, in the `## The module graph` section, directly after the final ```bash``` block that lists `module:graph --format=…` / `module:why amazon core`, add this paragraph:

```markdown
Local modules are referenced by their short name (`amazon`) — the package qualifies them with your
default vendor (derived from `modulesNamespace`, e.g. `happenv/amazon`). External packages keep their
full `vendor/name` (`acme/catalog`), which signals they aren't part of the local system. Resolution is
case-insensitive.
```

- [ ] **Step 2: Verify the bare-name examples are now accurate**

Confirm by eye that `module:why amazon core` and `module:graph --root=sale` in the README match the new behaviour (bare names accepted). No code change.

- [ ] **Step 3: Run the full suite once as a final gate**

Run: `vendor/bin/pest`
Expected: PASS (all suites green).

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "docs: clarify bare vs full module names in the README"
```

---

## Self-Review

**Spec coverage:**
- Rule (bare→qualified, slash→passthrough, case-insensitive) → Task 1 (`ModuleName`) + Task 3 (`resolve`).
- Default vendor from namespace + generator de-dup → Task 2.
- `ModuleLocator::resolve()`/`resolveOrFail()` + injected `defaultVendor` + binding → Task 3.
- Command integration for all five commands → Tasks 4 (impact/why/graph), 5 (seed), 6 (make:migration).
- `ModuleDescriptor::$path` as single source of truth for make:migration → Task 6.
- Multi-line error message → Task 3 (defined + tested), surfaced by Tasks 4–6.
- README accuracy → Task 7.
- Tests (unit + feature, fixtures use `myapp`, case-insensitivity) → Tasks 1, 3, 4, 5, 6.

**Type consistency:** `resolve(string): ?ModuleDescriptor` and `resolveOrFail(string): ModuleDescriptor` used identically across Tasks 3–6; constructor `AppModulesLocator(ModuleRegistry, string $defaultVendor, string $coreName = 'core')` matches the two updated call sites (Task 3 Step 1) and the binding (Task 3 Step 6); `ModuleDescriptor::$name` / `$path` are existing public readonly properties.

**Out of scope (Plan B):** display formatting (short vs full names) and the `--with-vendor` option — a separate spec/plan.
```
