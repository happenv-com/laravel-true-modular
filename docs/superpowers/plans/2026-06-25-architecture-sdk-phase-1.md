# Architecture SDK — Faza 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zbudować matematyczny fundament Architecture SDK i komendy `module:impact`, `module:why`, `module:graph` oparte na deterministycznym grafie zależności z `composer.json`.

**Architecture:** Przepływ `ArchitectureSource → ArchitectureIndexBuilder → immutable ArchitectureIndex → Analyzer → ArchitectureReport → ArchitectureRenderer`. `ArchitectureIndex` to czysta baza wiedzy bez logiki; analizatory operują na nim i zwracają immutable raporty; renderery są jedynie prezentacją (text/json/tree/mermaid/dot). JSON to wersjonowany kontrakt maszynowy.

**Tech Stack:** PHP 8.3, Laravel 12/13, Pest 4, orchestra/testbench, thecodingmachine/safe.

**Spec:** `docs/superpowers/specs/2026-06-25-architecture-introspection-sdk-design.md`

## Global Constraints

- PHP `^8.3`; każdy plik PHP zaczyna się od `declare(strict_types=1);`.
- Namespace nowego kodu: `Happenv\LaravelTrueModular\Architecture\...` (PSR-4, katalog `src/Architecture/`).
- Komendy w `Happenv\LaravelTrueModular\Commands\...` (katalog `src/Commands/`), rejestrowane w `src/KernelServiceProvider.php`.
- Determinizm: każda lista zależności/modułów sortowana alfabetycznie (`sort`/`ksort`) tam, gdzie algorytm nie narzuca kolejności. Ten sam input → identyczny output (w tym JSON).
- JSON każdego raportu zawiera blok `"schema": { "name": <string>, "version": <int> }`. Faza 1: `version = 1`.
- Klasy `final`. Value objecty i raporty `final readonly` (chyba że potrzebują cache — wtedy `final`).
- Typ composer modułu to `true-module` (`Application::getModuleComposerType()`).
- Nie zmieniamy publicznego API `ModuleTree` (kompatybilność wsteczna). Algorytmy grafowe są nową, osobną klasą.
- Brak placeholderów w kodzie produkcyjnym. Brak `laravel/ranger`.

---

## File Structure

**Nowe pliki produkcyjne:**
- `src/Architecture/Module/ModuleDescriptor.php` — immutable VO modułu (+`isCore()`)
- `src/Architecture/Module/ModuleLocator.php` — interfejs lokalizacji modułów
- `src/Architecture/Module/AppModulesLocator.php` — implementacja oparta o `ModuleTree`
- `src/Architecture/Graph/DependencyGraph.php` — czyste algorytmy grafowe (node = string)
- `src/Architecture/Source/ArchitectureContribution.php` — marker interface
- `src/Architecture/Source/ModulesContribution.php`
- `src/Architecture/Source/DependenciesContribution.php`
- `src/Architecture/Source/ArchitectureSource.php` — interfejs źródła
- `src/Architecture/Source/ComposerArchitectureSource.php`
- `src/Architecture/Index/ArchitectureIndex.php` — immutable baza wiedzy
- `src/Architecture/Index/ModuleQuery.php` — fluent query API
- `src/Architecture/Index/ArchitectureIndexBuilder.php` — merge źródeł
- `src/Architecture/Report/ArchitectureReport.php` — interfejs raportu
- `src/Architecture/Report/ImpactReport.php`
- `src/Architecture/Report/WhyReport.php`
- `src/Architecture/Report/GraphReport.php`
- `src/Architecture/Analyzer/ImpactAnalyzer.php`
- `src/Architecture/Analyzer/WhyAnalyzer.php`
- `src/Architecture/Analyzer/GraphAnalyzer.php`
- `src/Architecture/Renderer/ArchitectureRenderer.php` — interfejs renderera
- `src/Architecture/Renderer/UnsupportedFormatException.php`
- `src/Architecture/Renderer/JsonRenderer.php`
- `src/Architecture/Renderer/TextRenderer.php`
- `src/Architecture/Renderer/TreeRenderer.php`
- `src/Architecture/Renderer/MermaidRenderer.php`
- `src/Architecture/Renderer/DotRenderer.php`
- `src/Architecture/Renderer/RendererRegistry.php`
- `src/Commands/Concerns/RendersArchitectureReport.php` — trait wspólny dla komend
- `src/Commands/ModuleImpactCommand.php`
- `src/Commands/ModuleWhyCommand.php`
- `src/Commands/ModuleGraphCommand.php`

**Modyfikowane:**
- `composer.json` — `autoload-dev` + `orchestra/testbench` (Task 0)
- `src/KernelServiceProvider.php` — bindingi + rejestracja komend (Task 13)

**Testy / harness (Task 0 + per-task):**
- `phpunit.xml`, `tests/Pest.php`, `tests/TestCase.php`
- `tests/fixtures/app-modules/{kernel,core,pim,sale,amazon}/composer.json`
- `tests/fixtures/{bootstrap/cache,storage/framework/{cache,views,sessions},storage/logs}/.gitkeep`
- `tests/Unit/Architecture/*` — testy czystych klas (bez Laravela)
- `tests/Feature/*` — testy komend (testbench)

---

## Task 0: Test harness + fixtures

**Files:**
- Modify: `composer.json`
- Create: `phpunit.xml`
- Create: `tests/Pest.php`
- Create: `tests/TestCase.php`
- Create: `tests/fixtures/app-modules/kernel/composer.json`
- Create: `tests/fixtures/app-modules/core/composer.json`
- Create: `tests/fixtures/app-modules/pim/composer.json`
- Create: `tests/fixtures/app-modules/sale/composer.json`
- Create: `tests/fixtures/app-modules/amazon/composer.json`
- Create: `tests/fixtures/bootstrap/cache/.gitkeep`
- Create: `tests/fixtures/storage/framework/cache/.gitkeep`
- Create: `tests/fixtures/storage/framework/views/.gitkeep`
- Create: `tests/fixtures/storage/framework/sessions/.gitkeep`
- Create: `tests/fixtures/storage/logs/.gitkeep`
- Create: `tests/Unit/HarnessTest.php`
- Create: `tests/Feature/PackageBootTest.php`

**Interfaces:**
- Consumes: `Happenv\LaravelTrueModular\KernelServiceProvider`, `Happenv\LaravelTrueModular\ModuleSystem\ModuleTree`
- Produces: globalny helper `appModulesFixture(): string` (z `tests/Pest.php`); `Happenv\LaravelTrueModular\Tests\TestCase` (base path = `tests/fixtures`, `ModuleTree` zbindowany na fixtures); PSR-4 `Happenv\LaravelTrueModular\Tests\` → `tests/`

- [ ] **Step 1: Dodaj testbench i autoload-dev**

Run: `composer require --dev "orchestra/testbench" -W`

Następnie dodaj sekcję `autoload-dev` do `composer.json` (po bloku `autoload`):

```json
    "autoload-dev": {
        "psr-4": {
            "Happenv\\LaravelTrueModular\\Tests\\": "tests/"
        }
    },
```

Run: `composer dump-autoload`

- [ ] **Step 2: Utwórz `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 3: Utwórz `tests/TestCase.php`**

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Tests;

use Happenv\LaravelTrueModular\KernelServiceProvider;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getBasePath(): string
    {
        return __DIR__.'/fixtures';
    }

    /**
     * @param  Application  $app
     * @return array<int,class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [KernelServiceProvider::class];
    }

    /**
     * Bind ModuleTree to the fixtures so tests never depend on a real app.
     *
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app->singleton(
            ModuleTree::class,
            static fn (): ModuleTree => new ModuleTree(__DIR__.'/fixtures/app-modules'),
        );
    }
}
```

- [ ] **Step 4: Utwórz `tests/Pest.php`**

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Absolute path to the fixture app-modules directory.
 */
function appModulesFixture(): string
{
    return __DIR__.'/fixtures/app-modules';
}
```

- [ ] **Step 5: Utwórz fixture'y modułów**

`tests/fixtures/app-modules/kernel/composer.json`:

```json
{
    "name": "myapp/kernel",
    "type": "true-module",
    "require": {},
    "autoload": { "psr-4": { "Myapp\\Kernel\\": "src/" } }
}
```

`tests/fixtures/app-modules/core/composer.json`:

```json
{
    "name": "myapp/core",
    "type": "true-module",
    "version": "1.0.0",
    "require": { "myapp/kernel": "*" },
    "autoload": { "psr-4": { "Myapp\\Core\\": "src/" } }
}
```

`tests/fixtures/app-modules/pim/composer.json`:

```json
{
    "name": "myapp/pim",
    "type": "true-module",
    "require": { "myapp/core": "*" },
    "autoload": { "psr-4": { "Myapp\\Pim\\": "src/" } }
}
```

`tests/fixtures/app-modules/sale/composer.json`:

```json
{
    "name": "myapp/sale",
    "type": "true-module",
    "require": { "myapp/core": "*", "myapp/pim": "*" },
    "autoload": { "psr-4": { "Myapp\\Sale\\": "src/" } }
}
```

`tests/fixtures/app-modules/amazon/composer.json`:

```json
{
    "name": "myapp/amazon",
    "type": "true-module",
    "require": { "myapp/sale": "*" },
    "autoload": { "psr-4": { "Myapp\\Amazon\\": "src/" } }
}
```

Graf zależności (deps → dependents):
`kernel ← core ← pim ← sale ← amazon`, dodatkowo `sale` zależy bezpośrednio od `core`.

- [ ] **Step 6: Utwórz katalogi skeletonu dla testbench**

Run:
```bash
mkdir -p tests/fixtures/bootstrap/cache \
         tests/fixtures/storage/framework/cache \
         tests/fixtures/storage/framework/views \
         tests/fixtures/storage/framework/sessions \
         tests/fixtures/storage/logs
touch tests/fixtures/bootstrap/cache/.gitkeep \
      tests/fixtures/storage/framework/cache/.gitkeep \
      tests/fixtures/storage/framework/views/.gitkeep \
      tests/fixtures/storage/framework/sessions/.gitkeep \
      tests/fixtures/storage/logs/.gitkeep
```

- [ ] **Step 7: Napisz testy harnessu**

`tests/Unit/HarnessTest.php`:

```php
<?php

declare(strict_types=1);

it('runs a pure unit test without Laravel', function (): void {
    expect(1 + 1)->toBe(2);
});

it('exposes the fixture app-modules path', function (): void {
    expect(appModulesFixture())->toEndWith('tests/fixtures/app-modules')
        ->and(is_dir(appModulesFixture()))->toBeTrue();
});
```

`tests/Feature/PackageBootTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;

it('boots the package and resolves ModuleTree against fixtures', function (): void {
    $tree = app(ModuleTree::class);

    expect($tree)->toBeInstanceOf(ModuleTree::class)
        ->and($tree->getModuleNames())->toContain('myapp/core', 'myapp/amazon');
});
```

- [ ] **Step 8: Uruchom testy harnessu**

Run: `vendor/bin/pest tests/Unit/HarnessTest.php tests/Feature/PackageBootTest.php`
Expected: PASS (3 testy, brak błędów bootstrapu testbench).

> Fallback (jeśli `getBasePath()` powoduje błędy testbench): usuń override `getBasePath()`, a w `tests/Feature/PackageBootTest.php` korzystaj wyłącznie z `app(ModuleTree::class)` (zbindowany na fixtures w `getEnvironmentSetUp`). Legacy `ModuleTreeTest` (używający `ModuleTree::make()`/`base_path`) jest poza zakresem Fazy 1.

- [ ] **Step 9: Commit**

```bash
git add composer.json composer.lock phpunit.xml tests/
git commit -m "test: add testbench harness and module fixtures"
```

---

## Task 1: DependencyGraph

**Files:**
- Create: `src/Architecture/Graph/DependencyGraph.php`
- Test: `tests/Unit/Architecture/DependencyGraphTest.php`

**Interfaces:**
- Consumes: nic (czysta klasa; wejście to `array<string,array<string>>`)
- Produces: `DependencyGraph::__construct(array $adjacency)`; metody `nodes():array`, `has(string):bool`, `dependencies(string):array`, `dependents(string):array`, `transitiveDependencies(string):array`, `transitiveDependents(string):array`, `fanIn(string):int`, `fanOut(string):int`, `path(string,string):?array`, `dependencyDepth(string):int`. Wszystkie listy stringów sortowane alfabetycznie.

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/DependencyGraphTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;

function graphFixture(): DependencyGraph
{
    // deps: amazon -> sale -> {core, pim -> core}; core -> kernel
    return new DependencyGraph([
        'amazon' => ['sale'],
        'sale' => ['pim', 'core'],
        'pim' => ['core'],
        'core' => ['kernel'],
        'kernel' => [],
    ]);
}

it('lists nodes sorted', function (): void {
    expect(graphFixture()->nodes())->toBe(['amazon', 'core', 'kernel', 'pim', 'sale']);
});

it('returns direct dependencies sorted', function (): void {
    expect(graphFixture()->dependencies('sale'))->toBe(['core', 'pim']);
});

it('returns direct dependents sorted', function (): void {
    expect(graphFixture()->dependents('core'))->toBe(['pim', 'sale']);
});

it('computes transitive dependencies', function (): void {
    expect(graphFixture()->transitiveDependencies('amazon'))
        ->toBe(['core', 'kernel', 'pim', 'sale']);
});

it('computes transitive dependents', function (): void {
    expect(graphFixture()->transitiveDependents('core'))
        ->toBe(['amazon', 'pim', 'sale']);
});

it('computes fan-in and fan-out', function (): void {
    expect(graphFixture()->fanIn('core'))->toBe(2)
        ->and(graphFixture()->fanOut('sale'))->toBe(2);
});

it('finds the shortest dependency path', function (): void {
    expect(graphFixture()->path('amazon', 'core'))->toBe(['amazon', 'sale', 'core']);
});

it('returns null when no path exists', function (): void {
    expect(graphFixture()->path('core', 'amazon'))->toBeNull();
});

it('computes dependency depth as longest downstream chain', function (): void {
    expect(graphFixture()->dependencyDepth('amazon'))->toBe(4)
        ->and(graphFixture()->dependencyDepth('kernel'))->toBe(0);
});

it('does not loop on cycles', function (): void {
    $graph = new DependencyGraph(['a' => ['b'], 'b' => ['a']]);

    expect($graph->transitiveDependencies('a'))->toBe(['a', 'b'])
        ->and($graph->dependencyDepth('a'))->toBeGreaterThanOrEqual(0);
});
```

- [ ] **Step 2: Uruchom test — ma się wywalić**

Run: `vendor/bin/pest tests/Unit/Architecture/DependencyGraphTest.php`
Expected: FAIL ("Class ... DependencyGraph not found").

- [ ] **Step 3: Implementacja**

`src/Architecture/Graph/DependencyGraph.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Graph;

/**
 * Pure, module-agnostic dependency graph. Nodes are arbitrary strings.
 *
 * @template TNode of string
 */
final class DependencyGraph
{
    /** @var array<string, array<string>> node => sorted direct dependencies */
    private array $adjacency;

    /** @var array<string, array<string>>|null node => sorted direct dependents */
    private ?array $reverseCache = null;

    /**
     * @param  array<string, array<string>>  $adjacency
     */
    public function __construct(array $adjacency)
    {
        $normalized = [];

        foreach ($adjacency as $node => $deps) {
            $deps = array_values(array_unique($deps));
            sort($deps);
            $normalized[(string) $node] = $deps;

            foreach ($deps as $dep) {
                $normalized[$dep] ??= [];
            }
        }

        ksort($normalized);

        $this->adjacency = $normalized;
    }

    /** @return array<string> */
    public function nodes(): array
    {
        return array_keys($this->adjacency);
    }

    public function has(string $node): bool
    {
        return isset($this->adjacency[$node]);
    }

    /** @return array<string> */
    public function dependencies(string $node): array
    {
        return $this->adjacency[$node] ?? [];
    }

    /** @return array<string> */
    public function dependents(string $node): array
    {
        return $this->reverse()[$node] ?? [];
    }

    /** @return array<string> */
    public function transitiveDependencies(string $node): array
    {
        return $this->reachable($node, $this->adjacency);
    }

    /** @return array<string> */
    public function transitiveDependents(string $node): array
    {
        return $this->reachable($node, $this->reverse());
    }

    public function fanIn(string $node): int
    {
        return count($this->dependents($node));
    }

    public function fanOut(string $node): int
    {
        return count($this->dependencies($node));
    }

    /**
     * Shortest dependency path from $from to $to (inclusive endpoints), or null.
     *
     * @return array<string>|null
     */
    public function path(string $from, string $to): ?array
    {
        if (! $this->has($from) || ! $this->has($to)) {
            return null;
        }

        if ($from === $to) {
            return [$from];
        }

        $queue = [[$from]];
        $visited = [$from => true];

        while ($queue !== []) {
            $currentPath = array_shift($queue);
            $last = $currentPath[count($currentPath) - 1];

            foreach ($this->dependencies($last) as $dependency) {
                if ($dependency === $to) {
                    return [...$currentPath, $dependency];
                }

                if (! isset($visited[$dependency])) {
                    $visited[$dependency] = true;
                    $queue[] = [...$currentPath, $dependency];
                }
            }
        }

        return null;
    }

    /**
     * Longest downstream dependency chain length (in edges). Leaf => 0.
     */
    public function dependencyDepth(string $node): int
    {
        return $this->depth($node, []);
    }

    /**
     * @param  array<string, bool>  $stack
     */
    private function depth(string $node, array $stack): int
    {
        if (isset($stack[$node])) {
            return 0;
        }

        $stack[$node] = true;
        $max = 0;

        foreach ($this->dependencies($node) as $dependency) {
            $max = max($max, 1 + $this->depth($dependency, $stack));
        }

        return $max;
    }

    /**
     * @param  array<string, array<string>>  $graph
     * @return array<string>
     */
    private function reachable(string $node, array $graph): array
    {
        $result = [];
        $seen = [];
        $stack = $graph[$node] ?? [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;
            $result[] = $current;

            foreach ($graph[$current] ?? [] as $next) {
                if (! isset($seen[$next])) {
                    $stack[] = $next;
                }
            }
        }

        sort($result);

        return $result;
    }

    /** @return array<string, array<string>> */
    private function reverse(): array
    {
        if ($this->reverseCache !== null) {
            return $this->reverseCache;
        }

        $reverse = array_fill_keys(array_keys($this->adjacency), []);

        foreach ($this->adjacency as $node => $deps) {
            foreach ($deps as $dependency) {
                $reverse[$dependency][] = $node;
            }
        }

        foreach ($reverse as &$dependents) {
            sort($dependents);
        }
        unset($dependents);

        return $this->reverseCache = $reverse;
    }
}
```

> Uwaga do testu cyklu: `transitiveDependencies('a')` dla `a→b→a` zwraca `['a','b']`, bo `b` prowadzi z powrotem do `a` (a `a` jest osiągalne z `b`). To poprawne dla wykrywania osiągalności.

- [ ] **Step 4: Uruchom test — ma przejść**

Run: `vendor/bin/pest tests/Unit/Architecture/DependencyGraphTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Architecture/Graph/DependencyGraph.php tests/Unit/Architecture/DependencyGraphTest.php
git commit -m "feat: add pure DependencyGraph with deterministic algorithms"
```

---

## Task 2: ModuleDescriptor

**Files:**
- Create: `src/Architecture/Module/ModuleDescriptor.php`
- Test: `tests/Unit/Architecture/ModuleDescriptorTest.php`

**Interfaces:**
- Produces: `ModuleDescriptor::__construct(string $name, string $shortName, string $path, ?string $namespace, ?string $version, ?string $provider, array $require, bool $isCore)`; metody `isCore():bool`, `toArray():array`. Klucze `toArray()`: `name, shortName, path, namespace, version, provider, require, isCore`.

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/ModuleDescriptorTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function descriptorFixture(bool $core = false): ModuleDescriptor
{
    return new ModuleDescriptor(
        name: 'myapp/core',
        shortName: 'core',
        path: '/app-modules/core',
        namespace: 'Myapp\\Core\\',
        version: '1.0.0',
        provider: null,
        require: ['myapp/kernel' => '*'],
        isCore: $core,
    );
}

it('exposes immutable properties', function (): void {
    $descriptor = descriptorFixture();

    expect($descriptor->name)->toBe('myapp/core')
        ->and($descriptor->shortName)->toBe('core')
        ->and($descriptor->version)->toBe('1.0.0');
});

it('reports core status via isCore()', function (): void {
    expect(descriptorFixture(core: true)->isCore())->toBeTrue()
        ->and(descriptorFixture(core: false)->isCore())->toBeFalse();
});

it('serializes to array with stable keys', function (): void {
    expect(descriptorFixture()->toArray())->toBe([
        'name' => 'myapp/core',
        'shortName' => 'core',
        'path' => '/app-modules/core',
        'namespace' => 'Myapp\\Core\\',
        'version' => '1.0.0',
        'provider' => null,
        'require' => ['myapp/kernel' => '*'],
        'isCore' => false,
    ]);
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/ModuleDescriptorTest.php`
Expected: FAIL ("Class ... ModuleDescriptor not found").

- [ ] **Step 3: Implementacja**

`src/Architecture/Module/ModuleDescriptor.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Module;

final readonly class ModuleDescriptor
{
    /**
     * @param  array<string, string>  $require
     */
    public function __construct(
        public string $name,
        public string $shortName,
        public string $path,
        public ?string $namespace,
        public ?string $version,
        public ?string $provider,
        public array $require,
        public bool $isCore,
    ) {}

    public function isCore(): bool
    {
        return $this->isCore;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'shortName' => $this->shortName,
            'path' => $this->path,
            'namespace' => $this->namespace,
            'version' => $this->version,
            'provider' => $this->provider,
            'require' => $this->require,
            'isCore' => $this->isCore,
        ];
    }
}
```

- [ ] **Step 4: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/ModuleDescriptorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Architecture/Module/ModuleDescriptor.php tests/Unit/Architecture/ModuleDescriptorTest.php
git commit -m "feat: add ModuleDescriptor value object"
```

---

## Task 3: ModuleLocator + AppModulesLocator

**Files:**
- Create: `src/Architecture/Module/ModuleLocator.php`
- Create: `src/Architecture/Module/AppModulesLocator.php`
- Test: `tests/Unit/Architecture/AppModulesLocatorTest.php`

**Interfaces:**
- Consumes: `ModuleDescriptor` (Task 2); `Happenv\LaravelTrueModular\ModuleSystem\ModuleTree`
- Produces: `interface ModuleLocator { byClass(string):?ModuleDescriptor; byPath(string):?ModuleDescriptor; byComposerPackage(string):?ModuleDescriptor; all():array<string,ModuleDescriptor> }`. `AppModulesLocator::__construct(ModuleTree $tree, string $coreName = 'core')`. `all()` zwraca deskryptory posortowane po kluczu (composer package).

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/AppModulesLocatorTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;

function locatorFixture(): AppModulesLocator
{
    return new AppModulesLocator(new ModuleTree(appModulesFixture()));
}

it('returns all modules as descriptors keyed and sorted by package', function (): void {
    $all = locatorFixture()->all();

    expect(array_keys($all))->toBe(['myapp/amazon', 'myapp/core', 'myapp/kernel', 'myapp/pim', 'myapp/sale'])
        ->and($all['myapp/core'])->toBeInstanceOf(ModuleDescriptor::class)
        ->and($all['myapp/core']->shortName)->toBe('core')
        ->and($all['myapp/core']->version)->toBe('1.0.0')
        ->and($all['myapp/core']->require)->toHaveKey('myapp/kernel');
});

it('marks the core module via isCore()', function (): void {
    $all = locatorFixture()->all();

    expect($all['myapp/core']->isCore())->toBeTrue()
        ->and($all['myapp/sale']->isCore())->toBeFalse();
});

it('locates a module by composer package', function (): void {
    expect(locatorFixture()->byComposerPackage('myapp/sale')?->shortName)->toBe('sale')
        ->and(locatorFixture()->byComposerPackage('myapp/nope'))->toBeNull();
});

it('locates a module by class via psr-4 namespace', function (): void {
    expect(locatorFixture()->byClass('Myapp\\Sale\\Models\\Order')?->name)->toBe('myapp/sale')
        ->and(locatorFixture()->byClass('Other\\Thing'))->toBeNull();
});

it('locates a module by file path', function (): void {
    $path = appModulesFixture().'/pim/src/Models/Product.php';

    expect(locatorFixture()->byPath($path)?->name)->toBe('myapp/pim');
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/AppModulesLocatorTest.php`
Expected: FAIL ("Class ... AppModulesLocator not found").

- [ ] **Step 3: Implementacja interfejsu**

`src/Architecture/Module/ModuleLocator.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Module;

interface ModuleLocator
{
    public function byClass(string $class): ?ModuleDescriptor;

    public function byPath(string $path): ?ModuleDescriptor;

    public function byComposerPackage(string $package): ?ModuleDescriptor;

    /**
     * @return array<string, ModuleDescriptor> keyed by composer package
     */
    public function all(): array;
}
```

- [ ] **Step 4: Implementacja AppModulesLocator**

`src/Architecture/Module/AppModulesLocator.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Module;

use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

final class AppModulesLocator implements ModuleLocator
{
    /** @var array<string, ModuleDescriptor>|null */
    private ?array $descriptors = null;

    public function __construct(
        private readonly ModuleTree $moduleTree,
        private readonly string $coreName = 'core',
    ) {}

    public function byClass(string $class): ?ModuleDescriptor
    {
        $class = ltrim($class, '\\');
        $best = null;
        $bestLength = -1;

        foreach ($this->all() as $descriptor) {
            $namespace = $descriptor->namespace;

            if ($namespace === null) {
                continue;
            }

            if (str_starts_with($class, $namespace) && strlen($namespace) > $bestLength) {
                $best = $descriptor;
                $bestLength = strlen($namespace);
            }
        }

        return $best;
    }

    public function byPath(string $path): ?ModuleDescriptor
    {
        $path = $this->normalize($path);
        $best = null;
        $bestLength = -1;

        foreach ($this->all() as $descriptor) {
            $base = $this->normalize($descriptor->path);

            if (str_starts_with($path, $base.'/') && strlen($base) > $bestLength) {
                $best = $descriptor;
                $bestLength = strlen($base);
            }
        }

        return $best;
    }

    public function byComposerPackage(string $package): ?ModuleDescriptor
    {
        return $this->all()[$package] ?? null;
    }

    /**
     * @return array<string, ModuleDescriptor>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function all(): array
    {
        if ($this->descriptors !== null) {
            return $this->descriptors;
        }

        $descriptors = [];

        foreach ($this->moduleTree->getAllModules() as $name => $data) {
            $composer = $data['composer'];
            $shortName = $this->shortName($name);

            $psr4 = $composer['autoload']['psr-4'] ?? [];
            $namespace = $psr4 === [] ? null : $this->normalizeNamespace((string) array_key_first($psr4));

            $providers = $composer['extra']['laravel']['providers'] ?? [];

            $descriptors[$name] = new ModuleDescriptor(
                name: $name,
                shortName: $shortName,
                path: $data['path'],
                namespace: $namespace,
                version: isset($composer['version']) ? (string) $composer['version'] : null,
                provider: $providers === [] ? null : (string) $providers[0],
                require: $this->stringMap($composer['require'] ?? []),
                isCore: $shortName === $this->coreName,
            );
        }

        ksort($descriptors);

        return $this->descriptors = $descriptors;
    }

    private function shortName(string $package): string
    {
        $position = strrpos($package, '/');

        return $position === false ? $package : substr($package, $position + 1);
    }

    private function normalizeNamespace(string $namespace): string
    {
        return rtrim($namespace, '\\').'\\';
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function stringMap(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[(string) $key] = (string) $value;
        }

        return $result;
    }
}
```

- [ ] **Step 5: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/AppModulesLocatorTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Architecture/Module/ModuleLocator.php src/Architecture/Module/AppModulesLocator.php tests/Unit/Architecture/AppModulesLocatorTest.php
git commit -m "feat: add ModuleLocator and AppModulesLocator"
```

---

## Task 4: ArchitectureSource + contributions + ComposerArchitectureSource

**Files:**
- Create: `src/Architecture/Source/ArchitectureContribution.php`
- Create: `src/Architecture/Source/ModulesContribution.php`
- Create: `src/Architecture/Source/DependenciesContribution.php`
- Create: `src/Architecture/Source/ArchitectureSource.php`
- Create: `src/Architecture/Source/ComposerArchitectureSource.php`
- Test: `tests/Unit/Architecture/ComposerArchitectureSourceTest.php`

**Interfaces:**
- Consumes: `ModuleLocator`, `AppModulesLocator` (Task 3); `ModuleTree`; `ModuleDescriptor`
- Produces: `interface ArchitectureContribution {}` (marker); `final readonly ModulesContribution { array $modules }` (`array<string,ModuleDescriptor>`); `final readonly DependenciesContribution { array $edges }` (`array<string,array<string>>`); `interface ArchitectureSource { contribute(): iterable }` (`iterable<ArchitectureContribution>`); `ComposerArchitectureSource::__construct(ModuleTree $tree, ModuleLocator $locator)`.

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/ComposerArchitectureSourceTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Source\ComposerArchitectureSource;
use Happenv\LaravelTrueModular\Architecture\Source\DependenciesContribution;
use Happenv\LaravelTrueModular\Architecture\Source\ModulesContribution;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;

function composerSource(): ComposerArchitectureSource
{
    $tree = new ModuleTree(appModulesFixture());

    return new ComposerArchitectureSource($tree, new AppModulesLocator($tree));
}

it('yields a modules contribution and a dependencies contribution', function (): void {
    $contributions = iterator_to_array(composerSource()->contribute(), false);

    $modules = array_values(array_filter($contributions, fn ($c): bool => $c instanceof ModulesContribution));
    $deps = array_values(array_filter($contributions, fn ($c): bool => $c instanceof DependenciesContribution));

    expect($modules)->toHaveCount(1)
        ->and($deps)->toHaveCount(1)
        ->and(array_keys($modules[0]->modules))->toContain('myapp/sale')
        ->and($deps[0]->edges['myapp/sale'])->toContain('myapp/core', 'myapp/pim');
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/ComposerArchitectureSourceTest.php`
Expected: FAIL ("Class ... ComposerArchitectureSource not found").

- [ ] **Step 3: Implementacja kontraktów**

`src/Architecture/Source/ArchitectureContribution.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

interface ArchitectureContribution {}
```

`src/Architecture/Source/ModulesContribution.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

final readonly class ModulesContribution implements ArchitectureContribution
{
    /**
     * @param  array<string, ModuleDescriptor>  $modules
     */
    public function __construct(public array $modules) {}
}
```

`src/Architecture/Source/DependenciesContribution.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

final readonly class DependenciesContribution implements ArchitectureContribution
{
    /**
     * @param  array<string, array<string>>  $edges  node => direct dependencies
     */
    public function __construct(public array $edges) {}
}
```

`src/Architecture/Source/ArchitectureSource.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

interface ArchitectureSource
{
    /**
     * @return iterable<ArchitectureContribution>
     */
    public function contribute(): iterable;
}
```

- [ ] **Step 4: Implementacja ComposerArchitectureSource**

`src/Architecture/Source/ComposerArchitectureSource.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Source;

use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;

final class ComposerArchitectureSource implements ArchitectureSource
{
    public function __construct(
        private readonly ModuleTree $moduleTree,
        private readonly ModuleLocator $locator,
    ) {}

    public function contribute(): iterable
    {
        yield new ModulesContribution($this->locator->all());

        yield new DependenciesContribution($this->moduleTree->getDependencyGraph());
    }
}
```

- [ ] **Step 5: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/ComposerArchitectureSourceTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Architecture/Source tests/Unit/Architecture/ComposerArchitectureSourceTest.php
git commit -m "feat: add ArchitectureSource contracts and ComposerArchitectureSource"
```

---

## Task 5: ArchitectureIndex + ModuleQuery

**Files:**
- Create: `src/Architecture/Index/ModuleQuery.php`
- Create: `src/Architecture/Index/ArchitectureIndex.php`
- Test: `tests/Unit/Architecture/ArchitectureIndexTest.php`

**Interfaces:**
- Consumes: `ModuleDescriptor` (Task 2), `DependencyGraph` (Task 1)
- Produces:
  - `ArchitectureIndex::__construct(array $modules, DependencyGraph $graph)` (`array<string,ModuleDescriptor>`); metody `modules():ModuleQuery`, `module(string):?ModuleDescriptor`, `graph():DependencyGraph`, `has(string):bool`, `toArray():array`.
  - `ModuleQuery::__construct(array $modules, DependencyGraph $graph)`; metody `dependingOn(string):self`, `dependedOnBy(string):self`, `sortedByName():self`, `sortedByDepth():self`, `names():array<string>`, `get():array<ModuleDescriptor>`.

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/ArchitectureIndexTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ModuleQuery;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function descriptor(string $name, array $require = [], bool $core = false): ModuleDescriptor
{
    return new ModuleDescriptor(
        name: $name,
        shortName: substr($name, (int) strrpos($name, '/') + 1),
        path: '/app-modules/'.substr($name, (int) strrpos($name, '/') + 1),
        namespace: null,
        version: null,
        provider: null,
        require: $require,
        isCore: $core,
    );
}

function indexFixture(): ArchitectureIndex
{
    $modules = [
        'myapp/core' => descriptor('myapp/core', core: true),
        'myapp/pim' => descriptor('myapp/pim', ['myapp/core' => '*']),
        'myapp/sale' => descriptor('myapp/sale', ['myapp/pim' => '*']),
    ];

    $graph = new DependencyGraph([
        'myapp/core' => [],
        'myapp/pim' => ['myapp/core'],
        'myapp/sale' => ['myapp/pim'],
    ]);

    return new ArchitectureIndex($modules, $graph);
}

it('looks up a module and reports presence', function (): void {
    expect(indexFixture()->module('myapp/pim')?->name)->toBe('myapp/pim')
        ->and(indexFixture()->has('myapp/nope'))->toBeFalse();
});

it('returns a ModuleQuery from modules()', function (): void {
    expect(indexFixture()->modules())->toBeInstanceOf(ModuleQuery::class)
        ->and(indexFixture()->modules()->names())
        ->toBe(['myapp/core', 'myapp/pim', 'myapp/sale']);
});

it('queries modules depending on a module', function (): void {
    expect(indexFixture()->modules()->dependingOn('myapp/core')->names())
        ->toBe(['myapp/pim']);
});

it('sorts a query by dependency depth', function (): void {
    expect(indexFixture()->modules()->sortedByDepth()->names())
        ->toBe(['myapp/core', 'myapp/pim', 'myapp/sale']);
});

it('serializes to a deterministic array', function (): void {
    $array = indexFixture()->toArray();

    expect($array)->toHaveKeys(['modules', 'dependencies'])
        ->and(array_keys($array['dependencies']))->toBe(['myapp/core', 'myapp/pim', 'myapp/sale'])
        ->and($array['dependencies']['myapp/sale'])->toBe(['myapp/pim']);
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/ArchitectureIndexTest.php`
Expected: FAIL ("Class ... ArchitectureIndex not found").

- [ ] **Step 3: Implementacja ModuleQuery**

`src/Architecture/Index/ModuleQuery.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Index;

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

/**
 * Disciplined, immutable architectural query over modules.
 * Deliberately NOT a generic collection — only architectural filters.
 */
final readonly class ModuleQuery
{
    /**
     * @param  array<string, ModuleDescriptor>  $modules
     */
    public function __construct(
        private array $modules,
        private DependencyGraph $graph,
    ) {}

    public function dependingOn(string $module): self
    {
        $dependents = $this->graph->dependents($module);

        return $this->only($dependents);
    }

    public function dependedOnBy(string $module): self
    {
        $dependencies = $this->graph->dependencies($module);

        return $this->only($dependencies);
    }

    public function sortedByName(): self
    {
        $modules = $this->modules;
        ksort($modules);

        return new self($modules, $this->graph);
    }

    public function sortedByDepth(): self
    {
        $modules = $this->modules;

        uasort($modules, function (ModuleDescriptor $a, ModuleDescriptor $b): int {
            $depth = $this->graph->dependencyDepth($a->name) <=> $this->graph->dependencyDepth($b->name);

            return $depth !== 0 ? $depth : ($a->name <=> $b->name);
        });

        return new self($modules, $this->graph);
    }

    /** @return array<string> */
    public function names(): array
    {
        return array_keys($this->modules);
    }

    /** @return array<ModuleDescriptor> */
    public function get(): array
    {
        return array_values($this->modules);
    }

    /**
     * @param  array<string>  $names
     */
    private function only(array $names): self
    {
        $filtered = [];

        foreach ($names as $name) {
            if (isset($this->modules[$name])) {
                $filtered[$name] = $this->modules[$name];
            }
        }

        ksort($filtered);

        return new self($filtered, $this->graph);
    }
}
```

- [ ] **Step 4: Implementacja ArchitectureIndex**

`src/Architecture/Index/ArchitectureIndex.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Index;

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

final readonly class ArchitectureIndex
{
    /**
     * @param  array<string, ModuleDescriptor>  $modules
     */
    public function __construct(
        private array $modules,
        private DependencyGraph $graph,
    ) {}

    public function modules(): ModuleQuery
    {
        return (new ModuleQuery($this->modules, $this->graph))->sortedByName();
    }

    public function module(string $name): ?ModuleDescriptor
    {
        return $this->modules[$name] ?? null;
    }

    public function graph(): DependencyGraph
    {
        return $this->graph;
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $modules = [];
        $dependencies = [];

        foreach ($this->graph->nodes() as $name) {
            $dependencies[$name] = $this->graph->dependencies($name);
            $descriptor = $this->modules[$name] ?? null;
            $modules[$name] = $descriptor?->toArray();
        }

        return [
            'modules' => $modules,
            'dependencies' => $dependencies,
        ];
    }
}
```

- [ ] **Step 5: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/ArchitectureIndexTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Architecture/Index/ModuleQuery.php src/Architecture/Index/ArchitectureIndex.php tests/Unit/Architecture/ArchitectureIndexTest.php
git commit -m "feat: add ArchitectureIndex and ModuleQuery"
```

---

## Task 6: ArchitectureIndexBuilder

**Files:**
- Create: `src/Architecture/Index/ArchitectureIndexBuilder.php`
- Test: `tests/Unit/Architecture/ArchitectureIndexBuilderTest.php`

**Interfaces:**
- Consumes: `ArchitectureSource` (Task 4), kontrybucje (Task 4), `ArchitectureIndex`/`DependencyGraph`/`ModuleDescriptor`
- Produces: `ArchitectureIndexBuilder::__construct(iterable $sources)` (`iterable<ArchitectureSource>`); metoda `build(): ArchitectureIndex`. Merge przemienny i deterministyczny (`ksort` modułów i krawędzi, `array_unique` na krawędziach).

- [ ] **Step 1: Napisz failing test (w tym permutation test)**

`tests/Unit/Architecture/ArchitectureIndexBuilderTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;
use Happenv\LaravelTrueModular\Architecture\Source\ArchitectureSource;
use Happenv\LaravelTrueModular\Architecture\Source\DependenciesContribution;
use Happenv\LaravelTrueModular\Architecture\Source\ModulesContribution;

function descriptorFor(string $name): ModuleDescriptor
{
    return new ModuleDescriptor($name, $name, '/'.$name, null, null, null, [], false);
}

function sourceA(): ArchitectureSource
{
    return new class implements ArchitectureSource
    {
        public function contribute(): iterable
        {
            yield new ModulesContribution(['b/two' => descriptorFor('b/two'), 'a/one' => descriptorFor('a/one')]);
            yield new DependenciesContribution(['b/two' => ['a/one']]);
        }
    };
}

function sourceB(): ArchitectureSource
{
    return new class implements ArchitectureSource
    {
        public function contribute(): iterable
        {
            yield new ModulesContribution(['c/three' => descriptorFor('c/three')]);
            yield new DependenciesContribution(['c/three' => ['b/two']]);
        }
    };
}

it('builds an index merging all sources', function (): void {
    $index = (new ArchitectureIndexBuilder([sourceA(), sourceB()]))->build();

    expect($index)->toBeInstanceOf(ArchitectureIndex::class)
        ->and($index->modules()->names())->toBe(['a/one', 'b/two', 'c/three'])
        ->and($index->graph()->dependencies('c/three'))->toBe(['b/two']);
});

it('is order-independent (permutation test)', function (): void {
    $forward = (new ArchitectureIndexBuilder([sourceA(), sourceB()]))->build()->toArray();
    $reversed = (new ArchitectureIndexBuilder([sourceB(), sourceA()]))->build()->toArray();

    expect($reversed)->toBe($forward);
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/ArchitectureIndexBuilderTest.php`
Expected: FAIL ("Class ... ArchitectureIndexBuilder not found").

- [ ] **Step 3: Implementacja**

`src/Architecture/Index/ArchitectureIndexBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Index;

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Source\ArchitectureSource;
use Happenv\LaravelTrueModular\Architecture\Source\DependenciesContribution;
use Happenv\LaravelTrueModular\Architecture\Source\ModulesContribution;

final class ArchitectureIndexBuilder
{
    /**
     * @param  iterable<ArchitectureSource>  $sources
     */
    public function __construct(
        private readonly iterable $sources,
    ) {}

    public function build(): ArchitectureIndex
    {
        $modules = [];
        $edges = [];

        foreach ($this->sources as $source) {
            foreach ($source->contribute() as $contribution) {
                if ($contribution instanceof ModulesContribution) {
                    foreach ($contribution->modules as $name => $descriptor) {
                        $modules[$name] = $descriptor;
                    }

                    continue;
                }

                if ($contribution instanceof DependenciesContribution) {
                    foreach ($contribution->edges as $node => $deps) {
                        $edges[$node] = array_values(array_unique([
                            ...($edges[$node] ?? []),
                            ...$deps,
                        ]));
                    }
                }
            }
        }

        ksort($modules);
        ksort($edges);

        return new ArchitectureIndex($modules, new DependencyGraph($edges));
    }
}
```

- [ ] **Step 4: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/ArchitectureIndexBuilderTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Architecture/Index/ArchitectureIndexBuilder.php tests/Unit/Architecture/ArchitectureIndexBuilderTest.php
git commit -m "feat: add ArchitectureIndexBuilder with commutative merge"
```

---

## Task 7: ArchitectureReport + ImpactReport + ImpactAnalyzer

**Files:**
- Create: `src/Architecture/Report/ArchitectureReport.php`
- Create: `src/Architecture/Report/ImpactReport.php`
- Create: `src/Architecture/Analyzer/ImpactAnalyzer.php`
- Test: `tests/Unit/Architecture/ImpactAnalyzerTest.php`

**Interfaces:**
- Consumes: `ArchitectureIndex` (Task 5), `DependencyGraph` (Task 1)
- Produces:
  - `interface ArchitectureReport { schemaName():string; schemaVersion():int; toArray():array }`
  - `ImpactReport::__construct(string $module, array $direct, array $indirect)`; `total():int`; `schemaName()='impact'`; `schemaVersion()=1`; `toArray()` klucze: `module, direct, indirect, total`.
  - `ImpactAnalyzer::analyze(ArchitectureIndex $index, string $module): ImpactReport`. Rzuca `InvalidArgumentException` gdy moduł nie istnieje.

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/ImpactAnalyzerTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\ImpactAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function impactIndex(): ArchitectureIndex
{
    $names = ['core', 'pim', 'sale', 'amazon'];
    $modules = [];
    foreach ($names as $name) {
        $modules[$name] = new ModuleDescriptor($name, $name, '/'.$name, null, null, null, [], $name === 'core');
    }

    $graph = new DependencyGraph([
        'core' => [],
        'pim' => ['core'],
        'sale' => ['pim', 'core'],
        'amazon' => ['sale'],
    ]);

    return new ArchitectureIndex($modules, $graph);
}

it('separates direct and indirect impact', function (): void {
    $report = (new ImpactAnalyzer())->analyze(impactIndex(), 'core');

    expect($report->module)->toBe('core')
        ->and($report->direct)->toBe(['pim', 'sale'])
        ->and($report->indirect)->toBe(['amazon'])
        ->and($report->total())->toBe(3);
});

it('serializes with the impact schema name', function (): void {
    $report = (new ImpactAnalyzer())->analyze(impactIndex(), 'core');

    expect($report->schemaName())->toBe('impact')
        ->and($report->schemaVersion())->toBe(1)
        ->and($report->toArray())->toBe([
            'module' => 'core',
            'direct' => ['pim', 'sale'],
            'indirect' => ['amazon'],
            'total' => 3,
        ]);
});

it('throws for an unknown module', function (): void {
    (new ImpactAnalyzer())->analyze(impactIndex(), 'nope');
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/ImpactAnalyzerTest.php`
Expected: FAIL ("Class ... ImpactAnalyzer not found").

- [ ] **Step 3: Implementacja ArchitectureReport**

`src/Architecture/Report/ArchitectureReport.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Report;

interface ArchitectureReport
{
    public function schemaName(): string;

    public function schemaVersion(): int;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
```

- [ ] **Step 4: Implementacja ImpactReport**

`src/Architecture/Report/ImpactReport.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Report;

final readonly class ImpactReport implements ArchitectureReport
{
    /**
     * @param  array<string>  $direct
     * @param  array<string>  $indirect
     */
    public function __construct(
        public string $module,
        public array $direct,
        public array $indirect,
    ) {}

    public function total(): int
    {
        return count($this->direct) + count($this->indirect);
    }

    public function schemaName(): string
    {
        return 'impact';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'direct' => $this->direct,
            'indirect' => $this->indirect,
            'total' => $this->total(),
        ];
    }
}
```

- [ ] **Step 5: Implementacja ImpactAnalyzer**

`src/Architecture/Analyzer/ImpactAnalyzer.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Analyzer;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;
use InvalidArgumentException;

final class ImpactAnalyzer
{
    public function analyze(ArchitectureIndex $index, string $module): ImpactReport
    {
        if (! $index->has($module)) {
            throw new InvalidArgumentException(sprintf('Unknown module [%s].', $module));
        }

        $graph = $index->graph();

        $direct = $graph->dependents($module);
        $indirect = array_values(array_diff($graph->transitiveDependents($module), $direct));
        sort($indirect);

        return new ImpactReport($module, $direct, $indirect);
    }
}
```

- [ ] **Step 6: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/ImpactAnalyzerTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Architecture/Report/ArchitectureReport.php src/Architecture/Report/ImpactReport.php src/Architecture/Analyzer/ImpactAnalyzer.php tests/Unit/Architecture/ImpactAnalyzerTest.php
git commit -m "feat: add ArchitectureReport contract, ImpactReport and ImpactAnalyzer"
```

---

## Task 8: WhyReport + WhyAnalyzer

**Files:**
- Create: `src/Architecture/Report/WhyReport.php`
- Create: `src/Architecture/Analyzer/WhyAnalyzer.php`
- Test: `tests/Unit/Architecture/WhyAnalyzerTest.php`

**Interfaces:**
- Consumes: `ArchitectureIndex` (Task 5), `ArchitectureReport` (Task 7)
- Produces:
  - `WhyReport::__construct(string $from, string $to, ?array $path)`; `schemaName()='why'`; `schemaVersion()=1`; `toArray()` klucze: `from, to, path` (path = `array<string>|null`).
  - `WhyAnalyzer::analyze(ArchitectureIndex $index, string $from, string $to): WhyReport`. Rzuca `InvalidArgumentException` gdy `from`/`to` nie istnieją.

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/WhyAnalyzerTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\WhyAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function whyIndex(): ArchitectureIndex
{
    $modules = [];
    foreach (['core', 'pim', 'sale', 'amazon'] as $name) {
        $modules[$name] = new ModuleDescriptor($name, $name, '/'.$name, null, null, null, [], false);
    }

    $graph = new DependencyGraph([
        'core' => [],
        'pim' => ['core'],
        'sale' => ['pim'],
        'amazon' => ['sale'],
    ]);

    return new ArchitectureIndex($modules, $graph);
}

it('returns the dependency path from a module to its dependency', function (): void {
    $report = (new WhyAnalyzer())->analyze(whyIndex(), 'amazon', 'core');

    expect($report->from)->toBe('amazon')
        ->and($report->to)->toBe('core')
        ->and($report->path)->toBe(['amazon', 'sale', 'pim', 'core']);
});

it('returns null path when no dependency exists', function (): void {
    $report = (new WhyAnalyzer())->analyze(whyIndex(), 'core', 'amazon');

    expect($report->path)->toBeNull()
        ->and($report->toArray())->toBe(['from' => 'core', 'to' => 'amazon', 'path' => null]);
});

it('reports the why schema', function (): void {
    expect((new WhyAnalyzer())->analyze(whyIndex(), 'amazon', 'core')->schemaName())->toBe('why');
});

it('throws for unknown endpoints', function (): void {
    (new WhyAnalyzer())->analyze(whyIndex(), 'amazon', 'nope');
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/WhyAnalyzerTest.php`
Expected: FAIL ("Class ... WhyAnalyzer not found").

- [ ] **Step 3: Implementacja WhyReport**

`src/Architecture/Report/WhyReport.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Report;

final readonly class WhyReport implements ArchitectureReport
{
    /**
     * @param  array<string>|null  $path
     */
    public function __construct(
        public string $from,
        public string $to,
        public ?array $path,
    ) {}

    public function schemaName(): string
    {
        return 'why';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'path' => $this->path,
        ];
    }
}
```

- [ ] **Step 4: Implementacja WhyAnalyzer**

`src/Architecture/Analyzer/WhyAnalyzer.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Analyzer;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Report\WhyReport;
use InvalidArgumentException;

final class WhyAnalyzer
{
    public function analyze(ArchitectureIndex $index, string $from, string $to): WhyReport
    {
        foreach ([$from, $to] as $module) {
            if (! $index->has($module)) {
                throw new InvalidArgumentException(sprintf('Unknown module [%s].', $module));
            }
        }

        return new WhyReport($from, $to, $index->graph()->path($from, $to));
    }
}
```

- [ ] **Step 5: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/WhyAnalyzerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Architecture/Report/WhyReport.php src/Architecture/Analyzer/WhyAnalyzer.php tests/Unit/Architecture/WhyAnalyzerTest.php
git commit -m "feat: add WhyReport and WhyAnalyzer"
```

---

## Task 9: GraphReport + GraphAnalyzer

**Files:**
- Create: `src/Architecture/Report/GraphReport.php`
- Create: `src/Architecture/Analyzer/GraphAnalyzer.php`
- Test: `tests/Unit/Architecture/GraphAnalyzerTest.php`

**Interfaces:**
- Consumes: `ArchitectureIndex` (Task 5), `ArchitectureReport` (Task 7)
- Produces:
  - `GraphReport::__construct(array $roots, array $dependents, ?string $root)` (`roots: array<string>`, `dependents: array<string,array<string>>` node→sorted dependents); `schemaName()='graph'`; `schemaVersion()=1`; `toArray()` klucze: `root, roots, dependents`.
  - `GraphAnalyzer::analyze(ArchitectureIndex $index, ?string $root = null): GraphReport`. Bez `root`: roots = węzły bez zależności (sorted), dependents = pełna mapa. Z `root`: roots=[root], dependents ograniczone do poddrzewa osiągalnego przez relację „dependents". Rzuca `InvalidArgumentException` gdy `root` nie istnieje.

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/GraphAnalyzerTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\GraphAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleDescriptor;

function graphIndex(): ArchitectureIndex
{
    $modules = [];
    foreach (['core', 'pim', 'sale', 'amazon'] as $name) {
        $modules[$name] = new ModuleDescriptor($name, $name, '/'.$name, null, null, null, [], false);
    }

    $graph = new DependencyGraph([
        'core' => [],
        'pim' => ['core'],
        'sale' => ['pim'],
        'amazon' => ['sale'],
    ]);

    return new ArchitectureIndex($modules, $graph);
}

it('builds a full dependents graph rooted at leaves', function (): void {
    $report = (new GraphAnalyzer())->analyze(graphIndex());

    expect($report->roots)->toBe(['core'])
        ->and($report->dependents['core'])->toBe(['pim'])
        ->and($report->dependents['sale'])->toBe(['amazon']);
});

it('restricts the graph to a given root subtree', function (): void {
    $report = (new GraphAnalyzer())->analyze(graphIndex(), 'pim');

    expect($report->root)->toBe('pim')
        ->and($report->roots)->toBe(['pim'])
        ->and($report->dependents)->toHaveKeys(['pim', 'sale', 'amazon'])
        ->and($report->dependents)->not->toHaveKey('core');
});

it('reports the graph schema', function (): void {
    expect((new GraphAnalyzer())->analyze(graphIndex())->schemaName())->toBe('graph');
});

it('throws for an unknown root', function (): void {
    (new GraphAnalyzer())->analyze(graphIndex(), 'nope');
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/GraphAnalyzerTest.php`
Expected: FAIL ("Class ... GraphAnalyzer not found").

- [ ] **Step 3: Implementacja GraphReport**

`src/Architecture/Report/GraphReport.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Report;

final readonly class GraphReport implements ArchitectureReport
{
    /**
     * @param  array<string>  $roots
     * @param  array<string, array<string>>  $dependents  node => sorted dependents
     */
    public function __construct(
        public array $roots,
        public array $dependents,
        public ?string $root,
    ) {}

    public function schemaName(): string
    {
        return 'graph';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function toArray(): array
    {
        return [
            'root' => $this->root,
            'roots' => $this->roots,
            'dependents' => $this->dependents,
        ];
    }
}
```

- [ ] **Step 4: Implementacja GraphAnalyzer**

`src/Architecture/Analyzer/GraphAnalyzer.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Analyzer;

use Happenv\LaravelTrueModular\Architecture\Graph\DependencyGraph;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;
use InvalidArgumentException;

final class GraphAnalyzer
{
    public function analyze(ArchitectureIndex $index, ?string $root = null): GraphReport
    {
        $graph = $index->graph();

        if ($root !== null && ! $graph->has($root)) {
            throw new InvalidArgumentException(sprintf('Unknown module [%s].', $root));
        }

        if ($root !== null) {
            $nodes = [$root, ...$graph->transitiveDependents($root)];
        } else {
            $nodes = $graph->nodes();
        }

        $nodeSet = array_fill_keys($nodes, true);
        $dependents = [];

        foreach ($nodes as $node) {
            $dependents[$node] = array_values(array_filter(
                $graph->dependents($node),
                static fn (string $dependent): bool => isset($nodeSet[$dependent]),
            ));
        }

        ksort($dependents);

        if ($root !== null) {
            $roots = [$root];
        } else {
            $roots = array_values(array_filter(
                $nodes,
                static fn (string $node): bool => $graph->fanOut($node) === 0,
            ));
            sort($roots);
        }

        return new GraphReport($roots, $dependents, $root);
    }
}
```

- [ ] **Step 5: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/GraphAnalyzerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Architecture/Report/GraphReport.php src/Architecture/Analyzer/GraphAnalyzer.php tests/Unit/Architecture/GraphAnalyzerTest.php
git commit -m "feat: add GraphReport and GraphAnalyzer"
```

---

## Task 10: Renderer interface + JsonRenderer + RendererRegistry

**Files:**
- Create: `src/Architecture/Renderer/ArchitectureRenderer.php`
- Create: `src/Architecture/Renderer/UnsupportedFormatException.php`
- Create: `src/Architecture/Renderer/JsonRenderer.php`
- Create: `src/Architecture/Renderer/RendererRegistry.php`
- Test: `tests/Unit/Architecture/JsonRendererTest.php`
- Test: `tests/Unit/Architecture/RendererRegistryTest.php`

**Interfaces:**
- Consumes: `ArchitectureReport` (Task 7), `ImpactReport` (Task 7)
- Produces:
  - `interface ArchitectureRenderer { format():string; supports(ArchitectureReport):bool; render(ArchitectureReport):string }`
  - `UnsupportedFormatException extends RuntimeException`
  - `JsonRenderer` (`format()='json'`, `supports()=true` dla wszystkich). Output: pretty JSON z blokiem `schema` na górze, potem klucze z `toArray()`.
  - `RendererRegistry::__construct(array $renderers)` (`array<ArchitectureRenderer>`); `get(string $format, ArchitectureReport $report): ArchitectureRenderer` (rzuca `UnsupportedFormatException` gdy brak/nieobsługiwany).

- [ ] **Step 1: Napisz failing testy**

`tests/Unit/Architecture/JsonRendererTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\JsonRenderer;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;

it('wraps the report payload with a schema block', function (): void {
    $json = (new JsonRenderer())->render(new ImpactReport('core', ['pim'], ['amazon']));
    $decoded = json_decode($json, true);

    expect($decoded['schema'])->toBe(['name' => 'impact', 'version' => 1])
        ->and($decoded['module'])->toBe('core')
        ->and($decoded['direct'])->toBe(['pim'])
        ->and($decoded['total'])->toBe(2);
});

it('reports json as its format and supports any report', function (): void {
    $renderer = new JsonRenderer();

    expect($renderer->format())->toBe('json')
        ->and($renderer->supports(new ImpactReport('core', [], [])))->toBeTrue();
});
```

`tests/Unit/Architecture/RendererRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\JsonRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Renderer\UnsupportedFormatException;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;

it('resolves a renderer by format', function (): void {
    $registry = new RendererRegistry([new JsonRenderer()]);

    expect($registry->get('json', new ImpactReport('core', [], [])))
        ->toBeInstanceOf(JsonRenderer::class);
});

it('throws for an unknown format', function (): void {
    (new RendererRegistry([new JsonRenderer()]))->get('xml', new ImpactReport('core', [], []));
})->throws(UnsupportedFormatException::class);
```

- [ ] **Step 2: Uruchom testy — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/JsonRendererTest.php tests/Unit/Architecture/RendererRegistryTest.php`
Expected: FAIL ("Class ... not found").

- [ ] **Step 3: Implementacja interfejsu i wyjątku**

`src/Architecture/Renderer/ArchitectureRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;

interface ArchitectureRenderer
{
    public function format(): string;

    public function supports(ArchitectureReport $report): bool;

    public function render(ArchitectureReport $report): string;
}
```

`src/Architecture/Renderer/UnsupportedFormatException.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use RuntimeException;

final class UnsupportedFormatException extends RuntimeException {}
```

- [ ] **Step 4: Implementacja JsonRenderer**

`src/Architecture/Renderer/JsonRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;

use function Safe\json_encode;

final class JsonRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'json';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return true;
    }

    public function render(ArchitectureReport $report): string
    {
        $payload = [
            'schema' => [
                'name' => $report->schemaName(),
                'version' => $report->schemaVersion(),
            ],
            ...$report->toArray(),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
```

- [ ] **Step 5: Implementacja RendererRegistry**

`src/Architecture/Renderer/RendererRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;

final class RendererRegistry
{
    /** @var array<ArchitectureRenderer> */
    private array $renderers;

    /**
     * @param  iterable<ArchitectureRenderer>  $renderers
     */
    public function __construct(iterable $renderers)
    {
        $this->renderers = is_array($renderers) ? $renderers : iterator_to_array($renderers, false);
    }

    public function get(string $format, ArchitectureReport $report): ArchitectureRenderer
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->format() === $format && $renderer->supports($report)) {
                return $renderer;
            }
        }

        throw new UnsupportedFormatException(sprintf(
            'No renderer supports format [%s] for report [%s].',
            $format,
            $report->schemaName(),
        ));
    }
}
```

- [ ] **Step 6: Uruchom testy — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/JsonRendererTest.php tests/Unit/Architecture/RendererRegistryTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Architecture/Renderer/ArchitectureRenderer.php src/Architecture/Renderer/UnsupportedFormatException.php src/Architecture/Renderer/JsonRenderer.php src/Architecture/Renderer/RendererRegistry.php tests/Unit/Architecture/JsonRendererTest.php tests/Unit/Architecture/RendererRegistryTest.php
git commit -m "feat: add renderer contract, JsonRenderer and RendererRegistry"
```

---

## Task 11: TextRenderer

**Files:**
- Create: `src/Architecture/Renderer/TextRenderer.php`
- Test: `tests/Unit/Architecture/TextRendererTest.php`

**Interfaces:**
- Consumes: `ArchitectureRenderer` (Task 10), `ImpactReport`/`WhyReport`/`GraphReport`
- Produces: `TextRenderer` (`format()='text'`; `supports()` true dla `ImpactReport`/`WhyReport`/`GraphReport`). Render: czytelny tekst dla każdego z trzech raportów.

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/TextRendererTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\TextRenderer;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;
use Happenv\LaravelTrueModular\Architecture\Report\WhyReport;

it('renders an impact report as text with sections', function (): void {
    $text = (new TextRenderer())->render(new ImpactReport('core', ['pim', 'sale'], ['amazon']));

    expect($text)->toContain('core')
        ->and($text)->toContain('Direct:')
        ->and($text)->toContain('pim')
        ->and($text)->toContain('Indirect:')
        ->and($text)->toContain('amazon')
        ->and($text)->toContain('Total affected: 3');
});

it('renders a why path as an arrow chain', function (): void {
    $text = (new TextRenderer())->render(new WhyReport('amazon', 'core', ['amazon', 'sale', 'core']));

    expect($text)->toContain('amazon')
        ->and($text)->toContain('sale')
        ->and($text)->toContain('core');
});

it('renders a message when no path exists', function (): void {
    $text = (new TextRenderer())->render(new WhyReport('core', 'amazon', null));

    expect($text)->toContain('no dependency path');
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/TextRendererTest.php`
Expected: FAIL ("Class ... TextRenderer not found").

- [ ] **Step 3: Implementacja**

`src/Architecture/Renderer/TextRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;
use Happenv\LaravelTrueModular\Architecture\Report\WhyReport;

final class TextRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'text';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof ImpactReport
            || $report instanceof WhyReport
            || $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report): string
    {
        return match (true) {
            $report instanceof ImpactReport => $this->impact($report),
            $report instanceof WhyReport => $this->why($report),
            $report instanceof GraphReport => $this->graph($report),
            default => '',
        };
    }

    private function impact(ImpactReport $report): string
    {
        $lines = [$report->module, ''];

        $lines[] = 'Direct:';
        $lines = [...$lines, ...$this->indent($report->direct)];
        $lines[] = '';
        $lines[] = 'Indirect:';
        $lines = [...$lines, ...$this->indent($report->indirect)];
        $lines[] = '';
        $lines[] = sprintf('Total affected: %d', $report->total());

        return implode("\n", $lines);
    }

    private function why(WhyReport $report): string
    {
        if ($report->path === null) {
            return sprintf('%s has no dependency path to %s', $report->from, $report->to);
        }

        return implode("\n  ↓\n", $report->path);
    }

    private function graph(GraphReport $report): string
    {
        $lines = [];

        foreach ($report->roots as $root) {
            $this->appendTree($root, $report->dependents, '', $lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, array<string>>  $dependents
     * @param  array<string>  $lines
     */
    private function appendTree(string $node, array $dependents, string $prefix, array &$lines): void
    {
        $lines[] = $prefix.$node;

        foreach ($dependents[$node] ?? [] as $child) {
            $this->appendTree($child, $dependents, $prefix.'  ', $lines);
        }
    }

    /**
     * @param  array<string>  $items
     * @return array<string>
     */
    private function indent(array $items): array
    {
        if ($items === []) {
            return ['  -'];
        }

        return array_map(static fn (string $item): string => '  '.$item, $items);
    }
}
```

- [ ] **Step 4: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/TextRendererTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Architecture/Renderer/TextRenderer.php tests/Unit/Architecture/TextRendererTest.php
git commit -m "feat: add TextRenderer for impact, why and graph reports"
```

---

## Task 12: TreeRenderer + MermaidRenderer + DotRenderer

**Files:**
- Create: `src/Architecture/Renderer/TreeRenderer.php`
- Create: `src/Architecture/Renderer/MermaidRenderer.php`
- Create: `src/Architecture/Renderer/DotRenderer.php`
- Test: `tests/Unit/Architecture/GraphRenderersTest.php`

**Interfaces:**
- Consumes: `ArchitectureRenderer` (Task 10), `GraphReport` (Task 9)
- Produces: trzy renderery (`format()` = `tree`/`mermaid`/`dot`), każdy `supports()` tylko dla `GraphReport`. Tree = ASCII z `├──`/`└──`; Mermaid = `graph TD` + linie `A --> B`; Dot = `digraph { ... }` + linie `"A" -> "B"`.

> Zakres Fazy 1: renderery graficzne (tree/mermaid/dot) obsługują wyłącznie `GraphReport`. Rozszerzenie na impact/why będzie trywialne w kolejnej fazie (świadomy cut — patrz spec §5.10).

- [ ] **Step 1: Napisz failing test**

`tests/Unit/Architecture/GraphRenderersTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Renderer\DotRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\MermaidRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\TreeRenderer;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;
use Happenv\LaravelTrueModular\Architecture\Report\ImpactReport;

function graphReport(): GraphReport
{
    return new GraphReport(
        roots: ['core'],
        dependents: [
            'core' => ['pim'],
            'pim' => ['sale'],
            'sale' => [],
        ],
        root: null,
    );
}

it('renders an ascii tree', function (): void {
    $text = (new TreeRenderer())->render(graphReport());

    expect($text)->toContain('core')
        ->and($text)->toContain('└── pim')
        ->and($text)->toContain('└── sale');
});

it('renders mermaid edges', function (): void {
    $text = (new MermaidRenderer())->render(graphReport());

    expect($text)->toContain('graph TD')
        ->and($text)->toContain('core --> pim')
        ->and($text)->toContain('pim --> sale');
});

it('renders graphviz dot edges', function (): void {
    $text = (new DotRenderer())->render(graphReport());

    expect($text)->toContain('digraph')
        ->and($text)->toContain('"core" -> "pim"');
});

it('only supports graph reports', function (): void {
    $impact = new ImpactReport('core', [], []);

    expect((new TreeRenderer())->supports($impact))->toBeFalse()
        ->and((new MermaidRenderer())->supports(graphReport()))->toBeTrue();
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Unit/Architecture/GraphRenderersTest.php`
Expected: FAIL ("Class ... TreeRenderer not found").

- [ ] **Step 3: Implementacja TreeRenderer**

`src/Architecture/Renderer/TreeRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

final class TreeRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'tree';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report): string
    {
        /** @var GraphReport $report */
        $lines = [];

        foreach ($report->roots as $root) {
            $lines[] = $root;
            $this->children($root, $report->dependents, '', $lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, array<string>>  $dependents
     * @param  array<string>  $lines
     */
    private function children(string $node, array $dependents, string $prefix, array &$lines): void
    {
        $children = $dependents[$node] ?? [];
        $last = count($children) - 1;

        foreach ($children as $index => $child) {
            $isLast = $index === $last;
            $lines[] = $prefix.($isLast ? '└── ' : '├── ').$child;
            $this->children($child, $dependents, $prefix.($isLast ? '    ' : '│   '), $lines);
        }
    }
}
```

- [ ] **Step 4: Implementacja MermaidRenderer**

`src/Architecture/Renderer/MermaidRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

final class MermaidRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'mermaid';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report): string
    {
        /** @var GraphReport $report */
        $lines = ['graph TD', ''];

        foreach ($report->dependents as $node => $dependents) {
            foreach ($dependents as $dependent) {
                $lines[] = sprintf('%s --> %s', $node, $dependent);
            }
        }

        return implode("\n", $lines);
    }
}
```

- [ ] **Step 5: Implementacja DotRenderer**

`src/Architecture/Renderer/DotRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Renderer;

use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;
use Happenv\LaravelTrueModular\Architecture\Report\GraphReport;

final class DotRenderer implements ArchitectureRenderer
{
    public function format(): string
    {
        return 'dot';
    }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report): string
    {
        /** @var GraphReport $report */
        $lines = ['digraph {', ''];

        foreach ($report->dependents as $node => $dependents) {
            foreach ($dependents as $dependent) {
                $lines[] = sprintf('    "%s" -> "%s"', $node, $dependent);
            }
        }

        $lines[] = '';
        $lines[] = '}';

        return implode("\n", $lines);
    }
}
```

- [ ] **Step 6: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Unit/Architecture/GraphRenderersTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Architecture/Renderer/TreeRenderer.php src/Architecture/Renderer/MermaidRenderer.php src/Architecture/Renderer/DotRenderer.php tests/Unit/Architecture/GraphRenderersTest.php
git commit -m "feat: add tree, mermaid and dot graph renderers"
```

---

## Task 13: Command trait + service bindings

**Files:**
- Create: `src/Commands/Concerns/RendersArchitectureReport.php`
- Modify: `src/KernelServiceProvider.php`
- Test: `tests/Feature/ArchitectureBindingsTest.php`

**Interfaces:**
- Consumes: `ModuleLocator`/`AppModulesLocator`, `ComposerArchitectureSource`, `ArchitectureIndexBuilder`, `RendererRegistry`, wszystkie renderery, `ModuleTree`, `ArchitectureReport`
- Produces:
  - trait `RendersArchitectureReport` z metodami `protected output(ArchitectureReport $report, string $defaultFormat): int` oraz `protected schemaVersionValid(ArchitectureReport $report): bool`. Korzysta z `$this->option('format')`, `$this->option('schema-version')`, wstrzykniętego `RendererRegistry $this->renderers` i `$this->line(...)`.
  - Bindingi kontenera: `ModuleLocator::class` → `AppModulesLocator`; tag `architecture.sources` = `[ComposerArchitectureSource::class]`; `ArchitectureIndexBuilder::class` z `tagged('architecture.sources')`; `RendererRegistry::class` z 5 rendererami.

- [ ] **Step 1: Napisz failing test**

`tests/Feature/ArchitectureBindingsTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndex;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;

it('resolves the architecture services from the container', function (): void {
    expect(app(ModuleLocator::class))->toBeInstanceOf(ModuleLocator::class)
        ->and(app(RendererRegistry::class))->toBeInstanceOf(RendererRegistry::class);
});

it('builds a populated index through the container', function (): void {
    $index = app(ArchitectureIndexBuilder::class)->build();

    expect($index)->toBeInstanceOf(ArchitectureIndex::class)
        ->and($index->modules()->names())->toContain('myapp/sale', 'myapp/amazon');
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Feature/ArchitectureBindingsTest.php`
Expected: FAIL (`ModuleLocator` nie jest zbindowany / resolution error).

- [ ] **Step 3: Implementacja trait**

`src/Commands/Concerns/RendersArchitectureReport.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands\Concerns;

use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Renderer\UnsupportedFormatException;
use Happenv\LaravelTrueModular\Architecture\Report\ArchitectureReport;

trait RendersArchitectureReport
{
    protected function schemaVersionValid(ArchitectureReport $report): bool
    {
        $requested = (int) $this->option('schema-version');

        if ($requested !== $report->schemaVersion()) {
            $this->error(sprintf(
                'Unsupported schema version [%d]. Supported: %d.',
                $requested,
                $report->schemaVersion(),
            ));

            return false;
        }

        return true;
    }

    protected function output(ArchitectureReport $report, string $defaultFormat): int
    {
        if (! $this->schemaVersionValid($report)) {
            return self::FAILURE;
        }

        $format = (string) ($this->option('format') ?: $defaultFormat);

        /** @var RendererRegistry $registry */
        $registry = $this->renderers;

        try {
            $renderer = $registry->get($format, $report);
        } catch (UnsupportedFormatException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($renderer->render($report));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Zarejestruj bindingi w KernelServiceProvider**

Zmodyfikuj `src/KernelServiceProvider.php` — dodaj importy i rozszerz metodę `register()`. Pełna nowa treść pliku:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular;

use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\Architecture\Module\ModuleLocator;
use Happenv\LaravelTrueModular\Architecture\Renderer\DotRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\JsonRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\MermaidRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Architecture\Renderer\TextRenderer;
use Happenv\LaravelTrueModular\Architecture\Renderer\TreeRenderer;
use Happenv\LaravelTrueModular\Architecture\Source\ComposerArchitectureSource;
use Happenv\LaravelTrueModular\Commands\ListModulesCommand;
use Happenv\LaravelTrueModular\Commands\MakeMigrationCommand;
use Happenv\LaravelTrueModular\Commands\ModuleGraphCommand;
use Happenv\LaravelTrueModular\Commands\ModuleImpactCommand;
use Happenv\LaravelTrueModular\Commands\ModuleWhyCommand;
use Happenv\LaravelTrueModular\Commands\SeedModulesCommand;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleFileFinder;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

class KernelServiceProvider extends ServiceProvider
{
    public function initialize(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListModulesCommand::class,
                SeedModulesCommand::class,
                MakeMigrationCommand::class,
                ModuleImpactCommand::class,
                ModuleWhyCommand::class,
                ModuleGraphCommand::class,
            ]);
        }
    }

    #[Override]
    public function register(): void
    {
        $this->app->singleton(ModuleTree::class, static fn (): ModuleTree => ModuleTree::make());

        $this->app->singleton(ModuleFileFinder::class, static fn ($app): ModuleFileFinder => new ModuleFileFinder(
            $app->make(ModuleTree::class)
        ));

        $this->app->singleton(
            ModuleLocator::class,
            static fn (Application $app): AppModulesLocator => new AppModulesLocator($app->make(ModuleTree::class)),
        );

        $this->app->tag([ComposerArchitectureSource::class], 'architecture.sources');

        $this->app->bind(
            ArchitectureIndexBuilder::class,
            static fn (Application $app): ArchitectureIndexBuilder => new ArchitectureIndexBuilder(
                $app->tagged('architecture.sources'),
            ),
        );

        $this->app->singleton(
            RendererRegistry::class,
            static fn (Application $app): RendererRegistry => new RendererRegistry([
                $app->make(TextRenderer::class),
                $app->make(JsonRenderer::class),
                $app->make(TreeRenderer::class),
                $app->make(MermaidRenderer::class),
                $app->make(DotRenderer::class),
            ]),
        );
    }
}
```

> `ComposerArchitectureSource` jest autowire'owany (konstruktor: `ModuleTree`, `ModuleLocator`). Renderery nie mają zależności — kontener stworzy je sam.
> Uwaga: metoda `initialize()` jest wołana przez customowy `Application` tego pakietu (nie standardowy Laravel). W testbench (standardowy `Application`) komendy mogą nie zostać zarejestrowane przez `initialize()`. Dlatego komendy w testach Feature (Task 14-16) rejestrujemy w teście przez `$this->app->make(...Command...)` + `Artisan::registerCommand(...)` lub wywołujemy `handle()` bezpośrednio — patrz Task 14 Step 1.

- [ ] **Step 5: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Feature/ArchitectureBindingsTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Commands/Concerns/RendersArchitectureReport.php src/KernelServiceProvider.php tests/Feature/ArchitectureBindingsTest.php
git commit -m "feat: register architecture services and add command rendering trait"
```

---

## Task 14: module:impact command

**Files:**
- Create: `src/Commands/ModuleImpactCommand.php`
- Test: `tests/Feature/ModuleImpactCommandTest.php`

**Interfaces:**
- Consumes: `ArchitectureIndexBuilder`, `RendererRegistry`, `ImpactAnalyzer`, trait `RendersArchitectureReport`
- Produces: komenda `module:impact {module} {--format=} {--schema-version=1}`. Konstruktor: `(ArchitectureIndexBuilder $builder, ImpactAnalyzer $analyzer, RendererRegistry $renderers)`. Publiczna właściwość/pole `$renderers` wymagane przez trait. Domyślny format: `text`.

- [ ] **Step 1: Napisz failing test**

`tests/Feature/ModuleImpactCommandTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\ImpactAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ModuleImpactCommand;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ModuleImpactCommand(
        app(ArchitectureIndexBuilder::class),
        app(ImpactAnalyzer::class),
        app(RendererRegistry::class),
    ));
});

it('prints impact as text', function (): void {
    $this->artisan('module:impact', ['module' => 'myapp/core'])
        ->assertSuccessful()
        ->expectsOutputToContain('Total affected:');
});

it('prints impact as json with a schema block', function (): void {
    $this->artisan('module:impact', ['module' => 'myapp/core', '--format' => 'json'])
        ->assertSuccessful()
        ->expectsOutputToContain('"name": "impact"');
});

it('fails for an unsupported schema version', function (): void {
    $this->artisan('module:impact', ['module' => 'myapp/core', '--schema-version' => '2'])
        ->assertFailed()
        ->expectsOutputToContain('Unsupported schema version');
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Feature/ModuleImpactCommandTest.php`
Expected: FAIL ("Class ... ModuleImpactCommand not found").

- [ ] **Step 3: Implementacja**

`src/Commands/ModuleImpactCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Analyzer\ImpactAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\Concerns\RendersArchitectureReport;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Override;

final class ModuleImpactCommand extends Command
{
    use RendersArchitectureReport;

    #[Override]
    protected $signature = 'module:impact
                            {module : The module to analyze}
                            {--format= : Output format (text, json)}
                            {--schema-version=1 : Machine API schema version}';

    #[Override]
    protected $description = 'Show which modules are affected by a change to the given module';

    public function __construct(
        private readonly ArchitectureIndexBuilder $builder,
        private readonly ImpactAnalyzer $analyzer,
        protected RendererRegistry $renderers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = (string) $this->argument('module');

        try {
            $report = $this->analyzer->analyze($this->builder->build(), $module);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->output($report, 'text');
    }
}
```

- [ ] **Step 4: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Feature/ModuleImpactCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Commands/ModuleImpactCommand.php tests/Feature/ModuleImpactCommandTest.php
git commit -m "feat: add module:impact command"
```

---

## Task 15: module:why command

**Files:**
- Create: `src/Commands/ModuleWhyCommand.php`
- Test: `tests/Feature/ModuleWhyCommandTest.php`

**Interfaces:**
- Consumes: `ArchitectureIndexBuilder`, `RendererRegistry`, `WhyAnalyzer`, trait `RendersArchitectureReport`
- Produces: komenda `module:why {from} {to} {--format=} {--schema-version=1}`. Konstruktor: `(ArchitectureIndexBuilder $builder, WhyAnalyzer $analyzer, RendererRegistry $renderers)`. Domyślny format: `text`.

- [ ] **Step 1: Napisz failing test**

`tests/Feature/ModuleWhyCommandTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\WhyAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ModuleWhyCommand;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ModuleWhyCommand(
        app(ArchitectureIndexBuilder::class),
        app(WhyAnalyzer::class),
        app(RendererRegistry::class),
    ));
});

it('prints the dependency path between two modules', function (): void {
    $this->artisan('module:why', ['from' => 'myapp/amazon', 'to' => 'myapp/core'])
        ->assertSuccessful()
        ->expectsOutputToContain('myapp/sale');
});

it('fails for an unknown module', function (): void {
    $this->artisan('module:why', ['from' => 'myapp/amazon', 'to' => 'myapp/nope'])
        ->assertFailed()
        ->expectsOutputToContain('Unknown module');
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Feature/ModuleWhyCommandTest.php`
Expected: FAIL ("Class ... ModuleWhyCommand not found").

- [ ] **Step 3: Implementacja**

`src/Commands/ModuleWhyCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Analyzer\WhyAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\Concerns\RendersArchitectureReport;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Override;

final class ModuleWhyCommand extends Command
{
    use RendersArchitectureReport;

    #[Override]
    protected $signature = 'module:why
                            {from : The dependent module}
                            {to : The dependency module}
                            {--format= : Output format (text, json)}
                            {--schema-version=1 : Machine API schema version}';

    #[Override]
    protected $description = 'Explain why one module depends on another (shortest dependency path)';

    public function __construct(
        private readonly ArchitectureIndexBuilder $builder,
        private readonly WhyAnalyzer $analyzer,
        protected RendererRegistry $renderers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = (string) $this->argument('from');
        $to = (string) $this->argument('to');

        try {
            $report = $this->analyzer->analyze($this->builder->build(), $from, $to);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->output($report, 'text');
    }
}
```

- [ ] **Step 4: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Feature/ModuleWhyCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Commands/ModuleWhyCommand.php tests/Feature/ModuleWhyCommandTest.php
git commit -m "feat: add module:why command"
```

---

## Task 16: module:graph command

**Files:**
- Create: `src/Commands/ModuleGraphCommand.php`
- Test: `tests/Feature/ModuleGraphCommandTest.php`

**Interfaces:**
- Consumes: `ArchitectureIndexBuilder`, `RendererRegistry`, `GraphAnalyzer`, trait `RendersArchitectureReport`
- Produces: komenda `module:graph {--root=} {--format=} {--schema-version=1}`. Konstruktor: `(ArchitectureIndexBuilder $builder, GraphAnalyzer $analyzer, RendererRegistry $renderers)`. Domyślny format: `tree`. `--root` opcjonalny (null → cały graf).

- [ ] **Step 1: Napisz failing test**

`tests/Feature/ModuleGraphCommandTest.php`:

```php
<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Architecture\Analyzer\GraphAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\ModuleGraphCommand;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::registerCommand(new ModuleGraphCommand(
        app(ArchitectureIndexBuilder::class),
        app(GraphAnalyzer::class),
        app(RendererRegistry::class),
    ));
});

it('prints the dependency tree by default', function (): void {
    $this->artisan('module:graph')
        ->assertSuccessful()
        ->expectsOutputToContain('myapp/kernel');
});

it('prints mermaid output', function (): void {
    $this->artisan('module:graph', ['--format' => 'mermaid'])
        ->assertSuccessful()
        ->expectsOutputToContain('graph TD');
});

it('restricts the graph to a root', function (): void {
    $this->artisan('module:graph', ['--root' => 'myapp/sale', '--format' => 'json'])
        ->assertSuccessful()
        ->expectsOutputToContain('"root": "myapp/sale"');
});
```

- [ ] **Step 2: Uruchom test — FAIL**

Run: `vendor/bin/pest tests/Feature/ModuleGraphCommandTest.php`
Expected: FAIL ("Class ... ModuleGraphCommand not found").

- [ ] **Step 3: Implementacja**

`src/Commands/ModuleGraphCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Architecture\Analyzer\GraphAnalyzer;
use Happenv\LaravelTrueModular\Architecture\Index\ArchitectureIndexBuilder;
use Happenv\LaravelTrueModular\Architecture\Renderer\RendererRegistry;
use Happenv\LaravelTrueModular\Commands\Concerns\RendersArchitectureReport;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Override;

final class ModuleGraphCommand extends Command
{
    use RendersArchitectureReport;

    #[Override]
    protected $signature = 'module:graph
                            {--root= : Restrict the graph to this module subtree}
                            {--format= : Output format (tree, mermaid, dot, json)}
                            {--schema-version=1 : Machine API schema version}';

    #[Override]
    protected $description = 'Render the module dependency graph (tree, mermaid, dot, json)';

    public function __construct(
        private readonly ArchitectureIndexBuilder $builder,
        private readonly GraphAnalyzer $analyzer,
        protected RendererRegistry $renderers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $root = $this->option('root');
        $root = $root === null ? null : (string) $root;

        try {
            $report = $this->analyzer->analyze($this->builder->build(), $root);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->output($report, 'tree');
    }
}
```

- [ ] **Step 4: Uruchom test — PASS**

Run: `vendor/bin/pest tests/Feature/ModuleGraphCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Uruchom CAŁY zestaw + commit**

Run: `vendor/bin/pest`
Expected: PASS (wszystkie testy Unit + Feature zielone).

```bash
git add src/Commands/ModuleGraphCommand.php tests/Feature/ModuleGraphCommandTest.php
git commit -m "feat: add module:graph command"
```

---

## Self-Review (autor planu)

**Spec coverage (Faza 1):**
- ModuleDescriptor (+isCore) → Task 2 ✓
- ModuleLocator (byClass/byPath/byComposerPackage/all) → Task 3 ✓
- DependencyGraph<Node> generyczny, dependencyDepth jednoznaczne → Task 1 ✓
- ArchitectureSource + typowane Contribution + ComposerArchitectureSource → Task 4 ✓
- ArchitectureIndexBuilder (merge przemienny) + ArchitectureIndex + ModuleQuery → Task 5,6 ✓
- Analizatory Impact/Why/Graph → Task 7,8,9 ✓
- Reports + Stable Machine API (schema {name,version}, --schema-version) → Task 7-10,13 ✓
- Renderery text/json/tree/mermaid/dot → Task 10,11,12 ✓
- Komendy module:impact/why/graph → Task 14,15,16 ✓
- Determinizm + permutation test → Task 1 (sort), Task 6 (permutation) ✓
- Harness + fixtures (decyzja usera) → Task 0 ✓
- Kompatybilność wsteczna ModuleTree (nie zmieniane) ✓

**Świadome cuts Fazy 1 (poza zakresem, zgodnie ze spec):** metrics/doctor/describe/snapshot/context (Faza 2/3); renderery graficzne tylko dla GraphReport (rozszerzenie później); brak topologicalOrder/cycles w DependencyGraph (dochodzą w Fazie 2 dla doctor/boot).

**Placeholder scan:** brak TODO/TBD; każdy krok ma pełny kod i komendy. ✓

**Type consistency:** sygnatury analizatorów (`analyze(ArchitectureIndex, ...)`), raportów (`schemaName/schemaVersion/toArray`), rendererów (`format/supports/render`), builder (`build(): ArchitectureIndex`), trait (`output`, `$this->renderers`) spójne między taskami. ✓

## Execution Handoff

Plan zapisany w `docs/superpowers/plans/2026-06-25-architecture-sdk-phase-1.md`.
