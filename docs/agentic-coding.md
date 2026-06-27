# Agentic coding

This page is about working on a Laravel True Modular codebase with an AI coding agent — what makes
the architecture a good fit, and a concrete workflow that keeps an agent fast and inside the lines.

For *what* the package installs into [Laravel Boost](https://laravel.com/docs/boost) (the guideline
and the on-demand skill), see [laravel-boost.md](laravel-boost.md). This page is the *how*.

## Why modularity helps an agent

An LLM does its best work on a small, well-defined problem with explicit edges. A modular monolith
gives it exactly that:

- **Smaller context.** A module is a self-contained Composer package with an explicit `require` list.
  An agent can load just that module plus the few it depends on, rather than the whole application —
  cheaper, faster, and more accurate than reasoning over an undifferentiated `app/` tree.
- **Explicit edges instead of guesswork.** The dependency graph is data, not folklore. The agent can
  *ask* what a module depends on and what depends on it, instead of grepping the repo and inferring.
- **Hard guardrails.** The companion PHPStan
  [Module Boundary Enforcer](https://github.com/happenv-com/laravel-true-modular-phpstan) turns
  "don't reach across that boundary" into a failing check, so a wrong edit is caught by a command, not
  only by a human reviewer.

## The loop

A reliable agent workflow for a change in module `X`:

1. **Scope** — find the blast radius and the boundary you're allowed to touch.
2. **Understand** — confirm why the relevant edges exist.
3. **Load** — pull only `X` and its declared dependencies into context.
4. **Edit** — make the change inside `X`.
5. **Verify** — run PHPStan; the boundary enforcer fails if the edit crossed an undeclared edge.

Steps 1–2 are what this architecture adds over a flat app, so they're worth spelling out.

### 1. Scope with `module:impact`

Before changing a low-level module, ask what a change ripples out to:

```bash
php artisan module:impact acme/catalog
```

```
acme/catalog

Direct:
  acme/checkout
  acme/pricing

Indirect:
  acme/storefront

Total affected: 3
```

For an agent, prefer JSON — it carries a versioned schema and is trivial to parse:

```bash
php artisan module:impact acme/catalog --format=json
```

```json
{
    "schema": { "name": "impact", "version": 1 },
    "module": "acme/catalog",
    "direct": ["acme/checkout", "acme/pricing"],
    "indirect": ["acme/storefront"],
    "total": 3
}
```

`direct` + `indirect` is the set of modules the agent should re-check (and re-test) after the edit.

### 2. Understand an edge with `module:why`

When the agent sees a coupling it doesn't expect, get the shortest path that explains it:

```bash
php artisan module:why acme/checkout acme/catalog --format=json
```

```json
{
    "schema": { "name": "why", "version": 1 },
    "from": "acme/checkout",
    "to": "acme/catalog",
    "path": ["acme/checkout", "acme/catalog"]
}
```

`path` is `null` when there is no dependency — a fast way for the agent to confirm two modules are
*not* coupled before it assumes they are.

### 3. Load the right context

The files an agent needs for a change to `X` are:

- `X` itself (its `src/`, `config/`, `routes/`, `database/`, …), and
- the modules in `X`'s `composer.json` `require` — the only modules `X` is allowed to reference.

`module:graph --root=X` scopes the graph to that subtree so the agent can enumerate it instead of
loading everything:

```bash
php artisan module:graph --root=acme/catalog
```

Everything *downstream* of `X` (its dependents, from `module:impact`) is what to **verify** after the
change, not what to load for **making** it. Keeping those two sets distinct is what keeps context
small.

### 4–5. Edit, then let the enforcer check the boundary

After editing, run static analysis. The
[Module Boundary Enforcer](https://github.com/happenv-com/laravel-true-modular-phpstan) fails the
moment generated code references a class from a module that isn't in `X`'s `composer.json` `require`
(and flags any circular dependency it introduces). That's immediate, machine-readable feedback the
agent can act on in the same loop — the fix is either to declare the dependency or to not cross the
boundary.

```bash
vendor/bin/phpstan analyse
```

## Boost: the agent already knows the conventions

The package ships Boost resources so an agent understands the architecture without you pasting docs
into the prompt:

- an **always-on guideline** that anchors the agent (modular monolith, modules under `app-modules/`,
  providers extend `ModuleProvider`, the register → initialize → boot lifecycle), and
- an on-demand **`modular-monolith-development` skill** with the detailed patterns (the builder API,
  lifecycle placement, model extensions, config merging, dependency analysis, the do/don't list).

Both are auto-installed by `php artisan boost:install` and point back at this `docs/` directory. See
[laravel-boost.md](laravel-boost.md) for paths and how to keep them in sync.

## CI gates for generated code

The same commands that guide an agent locally double as gates for whatever it produces:

- **Blast-radius gate** — parse the `total` field of `module:impact <module> --format=json` and fail a
  job when a change reaches more modules than a threshold you set.
- **Boundary gate** — run PHPStan with the boundary enforcer in CI; an undeclared cross-module
  reference or a new cycle fails the build.

Together they make "the change stayed inside its module, or declared what it touched" a checkable
property rather than a review convention.

## See also

- [CLI commands](cli-commands.md) — full options for `module:impact` / `module:why` / `module:graph`.
- [Module dependencies](module-dependencies.md) — discovery, topological order, cycles.
- [Model extensions](model-extensions.md) — how a module extends another's domain model.
- [Laravel Boost integration](laravel-boost.md) — the guideline and skill this package ships.
