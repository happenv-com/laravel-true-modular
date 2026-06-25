# Architecture SDK — projekt

- **Data:** 2026-06-25
- **Pakiety:** `happenv-com/laravel-true-modular` (core), `filament-true-modular` (nowy)
- **Status:** design (do akceptacji przed planem implementacji)

---

## 1. Wizja

Budujemy **Architecture SDK** — warstwę wiedzy o architekturze aplikacji: model danych + API +
narzędzia. System zna architekturę, potrafi ją zweryfikować, opisać, zserializować, analizować i
udostępnić agentom AI. To znacznie więcej niż „system modułów" czy „kilka komend Artisan".

(Nazwa „SDK", a nie „Runtime" — Runtime sugeruje działanie w czasie requestu; tutaj 95% to analiza,
opis, eksport, diagnostyka. „SDK" oddaje trzy warstwy: model danych, API, narzędzia.)

Architektura aplikacji staje się **danymi**, konsumowanymi przez wiele warstw:

```
CLI  ·  AI/agenci  ·  IDE  ·  Dashboard  ·  CI
        ↑ wszyscy konsumują ten sam model danych ↑
```

CLI to tylko jeden z rendererów. JSON jest **oficjalnym, wersjonowanym kontraktem pakietu**, a
pozostałe renderery (`text`, `tree`, `mermaid`, `dot`) to wyłącznie prezentacje tego samego,
immutable modelu danych. Jedna logika biznesowa, wiele prezentacji.

## 2. Zasada nadrzędna

> **Discover as little as possible. Declare as much as possible.**

Indeks budujemy z **jawnych deklaracji**, które projekt już posiada:

- `composer.json` → `require` (zależności)
- `hasModelExtensions()`, `hasModelBuilderExtensions()` (rozszerzenia)
- `SchemaHook::register()` i pozostałe Bagi (hooki)

Konsekwencja: **determinizm**. Dwie identyczne aplikacje zawsze dają identyczny graf, boot order i
identyczny wynik każdej komendy. To największa przewaga względem narzędzi heurystycznych (np.
`laravel/ranger`) i świadomie jej bronimy. Żaden wynik nie może zależeć od konwencji katalogów,
kolejności ładowania klas ani refleksji „zgadującej" strukturę.

## 3. Architektura dwóch pakietów

### `laravel-true-modular` (core, framework-agnostic)

Cały Architecture SDK. **Zero zależności od Filamenta.** Zawiera:

- `ModuleLocator` + `ModuleDescriptor`
- `DependencyGraph<Node>` (uniwersalne algorytmy grafowe)
- interfejs `ArchitectureSource` + typowane `*Contribution` i źródła deklaratywne
- `ArchitectureIndexBuilder` → immutable `ArchitectureIndex` (+ `ModuleQuery`)
- analizatory (`ImpactAnalyzer`, `WhyAnalyzer`, …)
- raporty (`ArchitectureReport` i podtypy), renderery (`ArchitectureRenderer`)
- komendy `module:*`

### `filament-true-modular` (nowy, `/Users/bgajda/Packages/filament-true-modular`)

Wszystko, czego Filament potrzebuje do obsługi modułowości. Przenosimy tu Bagi z aplikacji Sellero
(`SchemaHook`, `ResourceExtension`, `TableExtension`, `NavigationChildItemBag`). Pakiet:

- zależy od `laravel-true-modular` (nie odwrotnie),
- dostarcza `HookArchitectureSource` implementujący `ArchitectureSource` z core,
- dzięki temu core widzi hooki/extensiony Filamenta, ale **nie zależy** od Filamenta.

```
filament-true-modular  ──depends on──▶  laravel-true-modular
   (Bagi, HookArchitectureSource)            (ArchitectureSource, Index, komendy)
```

## 4. Przepływ danych

Dwa rozdzielone etapy: **budowa wiedzy** i **analiza wiedzy**.

```
ArchitectureSource[]            ── każde zwraca iterable<*Contribution> (immutable)
        │
        ▼
ArchitectureIndexBuilder        ── merge wkładów wg typu
        │
        ▼
ArchitectureIndex (immutable)   ── czysta baza wiedzy, BEZ logiki analitycznej
        │
        ▼
Analyzer (Impact/Why/...)       ── logika; przyjmuje Index, zwraca raport
        │
        ▼
ArchitectureReport (immutable)
        │
        ▼
ArchitectureRenderer            ── text / json / tree / mermaid / dot
        │
        ▼
CLI / AI / IDE / Dashboard / CI
```

Komenda spina analizator z rendererem i nic więcej nie wie:

```php
$report = $impactAnalyzer->analyze($architectureIndex, 'inventory'); // immutable DTO
return $renderer->render($report);                                    // wybór po --format
```

Analogia: `ArchitectureIndex` to **AST** (czyste dane), `ArchitectureIndexBuilder` to **parser**,
analizatory to **passy** operujące na AST.

## 5. Komponenty

### 5.1 `ModuleDescriptor` (immutable value object)

```php
final readonly class ModuleDescriptor
{
    public string $name;        // composer package, np. "happenv/inventory"
    public string $shortName;   // "inventory"
    public string $path;        // root directory (absolute)
    public ?string $namespace;  // root namespace PSR-4
    public ?string $version;    // composer.json "version" (nullable)
    public ?string $provider;   // klasa providera modułu (nullable)
    /** @var array<string,string> */
    public array $require;      // surowe composer require

    public function isCore(): bool; // zamiast rozsianego `=== 'core'`
}
```

`isCore()` enkapsuluje „który moduł jest rdzeniem" (nazwa rdzenia konfigurowalna, domyślnie `core`).

### 5.2 `ModuleLocator`

```php
interface ModuleLocator
{
    public function byClass(string $class): ?ModuleDescriptor;
    public function byPath(string $path): ?ModuleDescriptor;
    public function byComposerPackage(string $package): ?ModuleDescriptor;

    /** @return array<string,ModuleDescriptor> klucz = composer package */
    public function all(): array;
}
```

Implementacja v1: `AppModulesLocator` — mapa z `app-modules/*` + autoload PSR-4 (reużywa mapowania
namespace→moduł z `ServiceProviderSorter`). Fundament **atrybucji do modułu** (klasa/ścieżka/closure
→ moduł) dla wszystkich źródeł. Interfejs pozwoli później dodać moduły z `vendor/`, pluginy itp.

### 5.3 `DependencyGraph<Node>` (uniwersalne algorytmy grafowe)

Całkowicie **niezależny od modułów**. Węzeł = dowolny `string` (moduły, workflow, DI, decision tables).

```php
/** @template TNode of string */
final class DependencyGraph
{
    public function dependencies(string $node): array;            // direct
    public function dependents(string $node): array;              // direct (graf odwrócony)
    public function transitiveDependencies(string $node): array;
    public function transitiveDependents(string $node): array;
    public function path(string $from, string $to): ?array;       // najkrótsza ścieżka (BFS)
    public function topologicalOrder(): array;                    // Kahn
    public function cycles(): array;                              // DFS
    public function dependencyDepth(string $node): int;           // longest downstream path (jednoznacznie)
    public function fanIn(string $node): int;                     // = count(dependents)
    public function fanOut(string $node): int;                    // = count(dependencies)
}
```

`dependencyDepth()` celowo jednoznaczne — to *najdłuższa ścieżka w dół* (nie „depth from root").
Algorytmy wydzielone z `ModuleTree` (które pozostaje warstwą odkrywania z `composer.json`, z
zachowaniem publicznego API). Wyniki **deterministyczne** — sortowanie alfabetyczne tam, gdzie
algorytm nie narzuca kolejności.

### 5.4 `ArchitectureSource` + typowane `*Contribution` (czyste źródła)

Źródła **nic nie mutują** — zwracają immutable wkłady; builder scala wg typu. Wkłady rozbite per
koncept, więc dodanie kolejnych (services, events, jobs…) nie puchnie jednego DTO.

```php
interface ArchitectureContribution {}                 // marker

final readonly class ModulesContribution implements ArchitectureContribution {
    /** @var array<string,ModuleDescriptor> */ public array $modules;
}
final readonly class DependenciesContribution implements ArchitectureContribution {
    /** @var array<string,array<string>> */ public array $edges;
}
final readonly class ExtensionsContribution implements ArchitectureContribution {
    /** @var array<string,array<ExtensionRecord>> */ public array $extensions;
}
final readonly class HooksContribution implements ArchitectureContribution {
    /** @var array<string,array<HookRecord>> */ public array $hooks;
}

interface ArchitectureSource
{
    /** @return iterable<ArchitectureContribution> */
    public function contribute(): iterable;
}
```

Źródła v1 (core):

- `ComposerArchitectureSource` → `ModulesContribution` + `DependenciesContribution` (z `ModuleTree`).
- `ExtensionArchitectureSource` → `ExtensionsContribution` z deklaracji
  `Module::$modelExtensions` / `$modelBuilderExtensions`; atrybucja przez `ModuleLocator`.

Źródło w `filament-true-modular`:

- `HookArchitectureSource` → `HooksContribution` z Bagów; atrybucja closure→moduł przez
  `ReflectionFunction::getFileName()` + `ModuleLocator::byPath()`.

Na przyszłość (poza zakresem): `RangerArchitectureSource` — opcjonalny adapter **tylko** po
ustabilizowaniu API Rangera. Nigdy jako fundament.

### 5.5 `ArchitectureIndexBuilder`

```php
$builder = new ArchitectureIndexBuilder(
    new ComposerArchitectureSource($moduleTree),
    new ExtensionArchitectureSource($moduleRegistry, $locator),
    // w aplikacji z Filamentem dorzucany przez kontener: new HookArchitectureSource($locator),
);

$index = $builder->build(); // zbiera iterable<Contribution>, merge wg typu → immutable Index
```

Builder dispatchuje wkłady po typie do odpowiednich „slotów", potem zamraża do `ArchitectureIndex`.
Nowy typ wkładu = nowa klasa + obsługa w builderze. Merge jest **przemienny** (kolejność źródeł nie
wpływa na wynik — patrz permutation test §10).

### 5.6 `ArchitectureIndex` (immutable baza wiedzy — BEZ logiki analitycznej)

```php
$index->modules(): ModuleQuery;                   // fluent query (§5.7)
$index->module(string): ?ModuleDescriptor;
$index->graph(): DependencyGraph;
$index->extensions(string $module): array;        // ExtensionRecord[]
$index->hooks(string $module): array;             // HookRecord[]
$index->toArray(): array;                          // czysta serializacja (snapshot)
```

Żadnych `impact()/why()/doctor()` — to robią analizatory. `toArray()` to serializacja modelu (nie
analiza).

### 5.7 `ModuleQuery` (Query API — zdyscyplinowane)

Fluent, immutable kolekcja modułów. **Tylko zapytania architektoniczne** — świadomie NIE robimy z
tego drugiego Eloquenta (`where/map/reduce/group/having`). Zamknięta lista metod:

```php
$index->modules()
    ->dependingOn('inventory')
    ->withExtension(Product::class)
    ->sortedByDepth()
    ->get();                             // ModuleDescriptor[]
```

Metody v1 (faza 1): `dependingOn`, `dependedOnBy`, `sortedByName`, `sortedByDepth`, `names`, `get`.
Rozszerzenie (faza 2/3): `withExtension`, `withHook`, `core`, `leaves`, `roots`, `providing` (gdy
powstanie Capability API). Każde dodanie metody = świadoma decyzja, nie automatyzm.

### 5.8 Analizatory (logika)

Każdy przyjmuje `ArchitectureIndex`, zwraca immutable raport. Index zostaje prostym modelem; logika
ewoluuje w małych, wyspecjalizowanych komponentach.

```php
$impactAnalyzer->analyze(ArchitectureIndex $i, string $module): ImpactReport;
$whyAnalyzer->analyze(ArchitectureIndex $i, string $from, string $to): WhyReport;
$graphAnalyzer->analyze(ArchitectureIndex $i, ?string $root = null): GraphReport;
$describeAnalyzer->analyze(ArchitectureIndex $i, string $module): DescribeReport;
$metricsAnalyzer->analyze(ArchitectureIndex $i, ?string $module = null): MetricsReport;
$doctorAnalyzer->analyze(ArchitectureIndex $i): DoctorReport;
$contextAnalyzer->analyze(ArchitectureIndex $i, string $module): ArchitectureContext; // faza 3
```

Przyszłe analizatory bez zmian w Index: `SecurityAnalyzer`, `ComplexityAnalyzer`,
`DeadModuleAnalyzer`, `CouplingAnalyzer`.

### 5.9 Raporty (`ArchitectureReport`)

Bazowy kontrakt + podtypy, wszystkie immutable, każdy z `toArray()` zawierającym blok `schema` (§7).
Podtypy: `ImpactReport`, `WhyReport`, `GraphReport`, `DescribeReport`, `MetricsReport`,
`DoctorReport`, `ArchitectureContext`.

### 5.10 Renderery (`ArchitectureRenderer`)

```php
interface ArchitectureRenderer
{
    public function format(): string;                 // "text"|"json"|"tree"|"mermaid"|"dot"
    public function supports(ArchitectureReport $r): bool;
    public function render(ArchitectureReport $r): string;
}
```

- `JsonRenderer` — uniwersalny; **kontrakt maszynowy**.
- `TextRenderer` — uniwersalny; sekcje jak w mockupach.
- `TreeRenderer`, `MermaidRenderer`, `DotRenderer` — raporty „grafowe" (`graph`, `impact`, `why`).

Renderer wybierany po `--format`; nieobsługiwana kombinacja → czytelny błąd.

## 6. Komendy

Prefiks `module:*`, wewnętrznie `Architecture*`. Wspólne opcje: `--format=`, `--schema-version=1`.

| Komenda | Argumenty | Format dom. | Faza | Uwagi |
|---|---|---|---|---|
| `module:impact` | `{module}` | `text` | 1 | Direct / Indirect / Total affected |
| `module:why` | `{from} {to}` | `text` | 1 | ścieżka zależności A→B |
| `module:graph` | `{--root=}` | `tree` | 1 | tree / mermaid / dot / json |
| `module:metrics` | `{module?}` | `text` | 2 | metryki architektury |
| `module:doctor` | — | `text` | 2 | INFO/WARN/ERROR + kody `TMxxx`; exit ≠ 0 przy ERROR |
| `module:describe` | `{module}` | `text` | 2 | sekcje deklaratywne |
| `module:snapshot` | — | `json` | 2 | pełny snapshot indeksu (CI diff) |
| `module:context` | `{module}` | `json` | 3 | kontekst pod AI (interpretacja) |

### 6.1 `module:impact` (priorytet #1)

```
Inventory

Direct:
  Sale
  Parcels

Indirect:
  Amazon Sale
  Amazon Fee
  Allegro Sale

Total affected: 18 modules
```

`Direct` = `dependents()`, `Indirect` = `transitiveDependents()` minus direct, `Total` = suma.

### 6.2 `module:why` (priorytet #2)

```
$ php artisan module:why amazon-sale product

amazon-sale
  ↓
sale
  ↓
inventory
  ↓
product
```

Najkrótsza ścieżka „dlaczego A zależy od B". Brak ścieżki → „no dependency path".

### 6.3 `module:graph` (priorytet #3)

`tree` (ASCII, domyślny), `mermaid`, `dot`, `json`. `--root` ogranicza do poddrzewa.

### 6.4 `module:metrics`

Wyłącznie **metryki architektury** (deterministyczne, z grafu). Nigdy statystyki plikowe.

```
Inventory
  Fan-in:                12
  Fan-out:                2
  Dependency depth:       3
  Transitive deps:        5
  Transitive dependents: 41
```

System-wide (bez argumentu): total modules, avg fan-out, max depth, avg dependency depth,
circular dependencies count.

### 6.5 `module:doctor`

Diagnostyka z poziomami i **stabilnymi kodami**. Check ma własny identyfikator:

```php
interface ArchitectureCheck
{
    public function code(): string;              // np. "TM001"
    /** @return Diagnostic[] */
    public function run(ArchitectureIndex $index): array;
}
// Diagnostic: level (INFO|WARN|ERROR), code (TMxxx), message, context
```

Katalog kodów v1 (rozszerzalny):

| Kod | Poziom | Znaczenie |
|---|---|---|
| `TM001` | ERROR | Circular dependency |
| `TM002` | ERROR | Unknown / missing module |
| `TM003` | ERROR | Duplicate / conflicting extension |
| `TM004` | WARN | Module unreachable from core |
| `TM005` | INFO | Average dependency depth |

CI:

```yaml
doctor:
  ignore: [TM004]
```

### 6.6 `module:describe`

Sekcje: Module, Dependencies, Dependents, Boot (pozycja w topo order), Extensions (model/builder),
Hooks (gdy obecny `HookArchitectureSource` — faza 3). Sekcja „Statistics" plikowa **świadomie
pominięta** (§8).

### 6.7 `module:snapshot` (CI diff)

Snapshot z metadanymi — przyszły artefakt CI:

```json
{
  "metadata": {
    "generated_at": "2026-06-25T10:00:00Z",
    "package_version": "0.x",
    "application_name": "sellero"
  },
  "schema": { "name": "architecture", "version": 1 },
  "architecture": {
    "modules": [],
    "dependencies": [],
    "extensions": [],
    "hooks": [],
    "metrics": []
  }
}
```

Workflow:

```
main ──▶ architecture.json   (baseline)
PR   ──▶ architecture.json   (diff)
```

```diff
+ inventory depends on addresses
- sale extends Product
```

**Diff CI celuje w blok `architecture`** (deterministyczny). `metadata.generated_at` jest z natury
niedeterministyczne — wyłączone z testu determinizmu. (Zastępuje wcześniejsze `module:export`.)

### 6.8 `module:context` (kontekst dla AI — faza 3)

Pierwszy komponent „nieobiektywny" (`related_modules` to interpretacja), dlatego po matematycznym
fundamencie:

```json
{
  "schema": { "name": "context", "version": 1 },
  "module": "inventory",
  "dependencies": ["core", "product", "addresses"],
  "public_api": ["..."],
  "extensions": ["Product via ProductModelExtension"],
  "hooks": ["ProductInfolistHook::Tabs"],
  "related_modules": ["sale", "parcels"]
}
```

`public_api` v1 = zadeklarowana powierzchnia (extensions + hooks + ewentualnie Capability API).

## 7. Stable Machine API (kontrakt JSON)

- Każda komenda przyjmuje `--schema-version` (v1: dozwolone `1`; inna → błąd).
- Każdy JSON zawiera **blok obiektowy** `schema { name, version }`:

```json
{ "schema": { "name": "architecture", "version": 1 } }
```

Kontrakty: `architecture` (snapshot), `impact`, `why`, `graph`, `metrics`, `doctor`, `describe`,
`context`. Każdy ewoluuje niezależnie; zmiana łamiąca = inkrement `version`. Schematy udokumentowane
przy implementacji (`docs/schema/v1/*.json`).

## 8. Poza zakresem (świadome decyzje)

- **Statystyki plikowe** (LOC, liczba modeli/serwisów/jobów/testów) — pominięte. To statystyki, nie
  metryki. Ewentualnie przyszły opcjonalny `FilesystemArchitectureSource` — nie teraz.
- **`laravel/ranger` jako fundament** — odrzucone (beta, app-wide, oparte na odkrywaniu). Może kiedyś
  jako opcjonalny `RangerArchitectureSource`.
- **Nowy generyczny `HookRegistry`** — zbędna abstrakcja; Bagi stają się źródłem przez
  `HookArchitectureSource`.

## 9. Kierunek przyszły: Capability API

Wartościowy kierunek, którego dziś jeszcze nie ma — **świadomie poza fundamentem** (dyscyplina
zakresu, §10/plan). Moduł deklaruje *kontrakty/usługi*, które udostępnia (to nie zależność):

```php
Module::provides([
    InventoryService::class,
    InventoryReservationService::class,
]);
```

Wówczas:

```
$ php artisan module:describe inventory

Provides
  InventoryService
  InventoryReservationService
```

Realizacja w istniejącym wzorcu: `ProvidesContribution` + `CapabilityArchitectureSource`, sekcja
„Provides" w `describe`, zapytanie `$index->modules()->providing(X)`. Dokładane jako osobne źródło,
gdy fundament się ustabilizuje.

## 10. Plan fazowy (dyscyplina zakresu)

> Najpierw mały, bardzo solidny matematyczny fundament. Potem **okres walidacji codziennym użyciem**.
> Dopiero potem kolejne źródła i analizatory. Wiele rzeczy wyjdzie dopiero w praktyce.

### Faza 1 — matematyczny fundament (graf)

- `ModuleDescriptor` (+`isCore()`), `ModuleLocator` (`AppModulesLocator`)
- `DependencyGraph<Node>` (wydzielenie algorytmów; `ModuleTree` bez zmian publicznego API)
- `ArchitectureSource` + `ModulesContribution`/`DependenciesContribution` + `ComposerArchitectureSource`
- `ArchitectureIndexBuilder` → `ArchitectureIndex` + `ModuleQuery` (wariant podstawowy)
- Analizatory: `ImpactAnalyzer`, `WhyAnalyzer`, `GraphAnalyzer`
- DTO: `ImpactReport`, `WhyReport`, `GraphReport`
- Renderery: `text`, `json`, `tree`, `mermaid`, `dot`
- Komendy: `module:impact`, `module:why`, `module:graph`
- Stable Machine API (`--schema-version`, blok `schema`)

**→ Okres walidacji: używać Fazy 1 w codziennej pracy przed rozpoczęciem Fazy 2.**

### Faza 2 — metryki, diagnostyka, snapshot

- `ExtensionArchitectureSource` (+ `ExtensionsContribution`)
- `ModuleQuery` — filtry rozszerzone (`withExtension`, `core`, `leaves`, `roots`)
- Analizatory: `MetricsAnalyzer`, `DoctorAnalyzer` (+ `ArchitectureCheck` z `code()`, katalog kodów),
  `DescribeAnalyzer`
- DTO: `MetricsReport`, `DoctorReport`, `DescribeReport`
- Komendy: `module:metrics`, `module:doctor`, `module:describe`, `module:snapshot`

### Faza 3 — Filament (hooki), kontekst AI

- Pakiet `filament-true-modular`: Bagi + `HookArchitectureSource` (+ `HooksContribution`)
- `ContextAnalyzer` + `module:context`
- Wzbogacenie `describe`/`doctor`/`context` o Hooks
- (Opcjonalnie później) Capability API — §9

## 11. Testy (Pest)

- Jednostkowe: `DependencyGraph` (fixture'y: liniowy, rozgałęziony, z cyklem; fan-in/out,
  dependencyDepth, transitive, path, cycles), `ModuleLocator`, `ArchitectureIndexBuilder`, analizatory.
- Renderery: testy snapshot (text/tree/mermaid/dot/json) na ustalonym `ArchitectureIndex`.
- Komendy: integracyjne na fixture `app-modules` (core → pim → sale → amazon).
- **Test determinizmu**: to samo wejście → identyczny JSON (blok `architecture`).
- **Permutation test**: te same źródła w różnej kolejności (`[Composer, Extensions, Hooks]` vs
  `[Hooks, Composer, Extensions]`) → identyczny `ArchitectureIndex` i identyczny JSON.
- Kontrakt: walidacja JSON względem schematu v1; `--schema-version=2` → błąd.

## 12. Kompatybilność wsteczna

- `modules:list`, `modules:seed`, `module:make:migration` — bez zmian.
- `ModuleTree` zachowuje publiczne API; algorytmy reużyte/wydzielone do `DependencyGraph`.

## 13. Decyzje zamknięte

- Nazwa: **Architecture SDK** (nie „Runtime").
- Analizatory wydzielone z `ArchitectureIndex` (Index = czysta baza wiedzy).
- `DependencyGraph` generyczny (węzeł = string); `dependencyDepth()` jednoznaczne.
- `ModuleDescriptor::isCore()`; nazwa typu z lokatora: `ModuleDescriptor`.
- `ArchitectureSource` zwraca `iterable<*Contribution>`; wkłady typowane per koncept; merge przemienny.
- `ModuleQuery` zdyscyplinowane (zamknięta lista metod, nie drugi Eloquent).
- Doctor: poziomy + stabilne kody `TMxxx`; `ArchitectureCheck::code()`.
- Stable Machine API: blok `schema { name, version }`.
- `module:snapshot` z `metadata`/`schema`/`architecture`; diff CI w bloku `architecture`.
- `ContextAnalyzer`/`module:context` → faza 3 (interpretacja po fundamencie).
- Capability API — udokumentowany kierunek przyszły, poza fundamentem.
