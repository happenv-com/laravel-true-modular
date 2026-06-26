# Testing

The package's own test suite uses [Pest 4](https://pestphp.com) on top of
`orchestra/testbench`. The same patterns apply when testing modules in your own app.

## Running the suite

```bash
vendor/bin/pest                                   # everything
vendor/bin/pest tests/Feature/ModuleTreeTest.php  # one file
vendor/bin/pest --filter "sorts providers"        # one test by name
vendor/bin/pest --testsuite Unit                  # Unit or Feature (see phpunit.xml)
```

Static analysis and formatting:

```bash
vendor/bin/pint               # format
vendor/bin/phpstan analyse    # level 6 + larastan
```

## Fixture modules

Feature tests run against a fixed set of fixture modules under `tests/fixtures/app-modules/`:

```
kernel ← core ← pim ← sale ← amazon
```

`tests/TestCase.php` overrides `applicationBasePath()` to point at `tests/fixtures/`, so
`base_path('app-modules')` resolves to the fixtures and `ModuleTree::make()` /
`ModuleFileFinder::make()` work unchanged. `getEnvironmentSetUp()` additionally rebinds `ModuleTree`
to that fixture path. The `appModulesFixture()` helper (in `tests/Pest.php`) returns the fixture path.

Only `Feature` tests `use(TestCase::class)`; `Unit` tests are plain and construct their subjects
directly (e.g. `new DependencyGraph([...])`).

## Two ways to test

**Unit — feed data directly.** The graph and algorithm types are pure and need no Laravel:

```php
$graph = new DependencyGraph(['amazon' => ['sale'], 'sale' => ['core'], 'core' => []]);

expect($graph->topologicalOrder())->toBe(['core', 'sale', 'amazon']);
```

**Feature — drive the real components / commands.** Build the index or invoke a command and assert on
its output. Architecture commands construct cleanly from the container:

```php
use Illuminate\Support\Facades\Artisan;

Artisan::registerCommand(new ListModulesCommand(
    app(ModuleTree::class),
    app(ArchitectureIndexBuilder::class),
    app(RendererRegistry::class),
));

$exit = Artisan::call('module:list', ['--format' => 'json']);
$json = json_decode(Artisan::output(), associative: true);

expect($exit)->toBe(0)->and($json['modules'][0]['name'])->toBe('myapp/kernel');
```

> Use `Artisan::call()` + `Artisan::output()` together when you need to parse output. The
> `$this->artisan()` pending-command helper writes to a separate buffer, so mixing the two yields an
> empty `Artisan::output()`.

## Adding a fixture module

To exercise new graph or lifecycle behaviour, add a directory under `tests/fixtures/app-modules/`
with a `composer.json` (`type: "true-module"`, real `require` deps), and register it in the `classmap`
autoload in the package `composer.json` so its classes load. Because `kernel` is the only dependency-
free module, ordering assertions can rely on it being first.

## Reset global state between tests

Model extensions use a process-wide static registry (`AttributeResolversBag`). If a test registers
extensions, call `AttributeResolversBag::flush()` in teardown to keep tests isolated. See
[model-extensions.md](model-extensions.md).
