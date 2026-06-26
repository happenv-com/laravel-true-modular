# Extending the package

The package exposes three open/closed extension seams. In each case you add a class and register
it — you never edit existing package code.

## 1. Add a module feature

Every builder feature is a **pair of traits kept in lockstep**:

- a `Concerns/Package/Has*` trait that stores the declaration on `Module`;
- a `Concerns/PackageServiceProvider/Process*` trait that consumes it during the lifecycle.

To add one, write both halves and wire the `process*()` call into the right phase of
`ModuleProvider::register() / initialize() / boot()`.

```php
// 1. Declaration — Concerns/Package/HasSitemaps.php
trait HasSitemaps
{
    /** @var string[] */
    public array $sitemapFileNames = [];

    public function hasSitemap(string $name): static
    {
        $this->sitemapFileNames[] = $name;

        return $this;
    }
}

// 2. Processor — Concerns/PackageServiceProvider/ProcessSitemaps.php
trait ProcessSitemaps
{
    protected function processSitemaps(): self
    {
        if (blank($this->module->sitemapFileNames)) {
            return $this;
        }

        foreach ($this->module->sitemapFileNames as $name) {
            require_once $this->module->vendorPath('routes/'.$name.'.php');
        }

        return $this;
    }
}
```

Then `use` both traits — `HasSitemaps` on `Module`, `ProcessSitemaps` on `ModuleProvider` — and
add `->processSitemaps()` to the fluent chain in the correct phase (`boot()` for routes/views/
commands, `initialize()` for cross-module wiring, `register()` for bindings).

Conventions to match the existing features:

- Resolve shipped paths with `Module::vendorPath('<dir>/<file>')`, never hand-built `/../` strings.
- Open every processor with a `blank(...)` / `! $this->module->hasX` guard that returns `$this`.
- Provide a plural `hasXs(string ...$values)` accumulator using the `MergesFlattened` concern so
  callers can pass spread args or an array.

See [builders.md](builders.md) for the full catalogue of existing pairs.

## 2. Add an architecture renderer

Renderers are resolved from the container tag `architecture.renderers` and selected by matching
`format()` **and** `supports($report)`. Implement `ArchitectureRenderer` and tag it:

```php
final class HtmlGraphRenderer implements ArchitectureRenderer
{
    public function format(): string { return 'html'; }

    public function supports(ArchitectureReport $report): bool
    {
        return $report instanceof GraphReport;
    }

    public function render(ArchitectureReport $report): string { /* ... */ }
}

// In a service provider:
$this->app->tag([HtmlGraphRenderer::class], 'architecture.renderers');
```

`module:graph --format=html` now works with **no change** to the package. Because selection is by
`format()`+`supports()`, you can also provide a renderer for an existing format but a new report
type (e.g. a `text` renderer for a report you introduce). For graph output, reuse the
`Support/DependentsTreeWalker` (tree traversal) and `Support/EmitsGraphEdges` (edge enumeration)
helpers rather than re-implementing them.

## 3. Add an architecture data source

The `ArchitectureIndex` is built from every service tagged `architecture.sources`. A source
implements `ArchitectureSource::contribute()` and yields contributions (`ModulesContribution`,
`DependenciesContribution`). Register it by tagging:

```php
final class StaticAnalysisSource implements ArchitectureSource
{
    public function contribute(): iterable
    {
        yield new DependenciesContribution($edgesDiscoveredByStaticAnalysis);
    }
}

$this->app->tag([StaticAnalysisSource::class], 'architecture.sources');
```

`ArchitectureIndexBuilder` merges all sources order-independently, so a new source composes with
`ComposerArchitectureSource` without any coordination.

> Adding a brand-new *kind* of contribution (beyond modules/dependencies) is also supported via the
> contribution's own `applyTo()` method — covered in [architecture-runtime.md](architecture-runtime.md).
