<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;

describe('ModuleRegistry', function (): void {
    describe('getAllModules', function (): void {
        it('discovers all modules in app-modules directory', function (): void {
            $tree = ModuleRegistry::make();
            $modules = $tree->getAllModules();

            expect($modules)->toBeArray()
                ->and($modules)->toHaveKey('myapp/core')
                ->and($modules)->toHaveKey('myapp/sale')
                ->and($modules)->toHaveKey('myapp/pim');
        });

        it('returns module data with name, path, and composer', function (): void {
            $tree = ModuleRegistry::make();
            $modules = $tree->getAllModules();

            $coreModule = $modules['myapp/core'];

            expect($coreModule)->toHaveKey('name')
                ->and($coreModule)->toHaveKey('path')
                ->and($coreModule)->toHaveKey('composer')
                ->and($coreModule['name'])->toBe('myapp/core')
                ->and($coreModule['path'])->toContain('app-modules/core');
        });
    });

    describe('getModuleNames', function (): void {
        it('returns array of module names', function (): void {
            $tree = ModuleRegistry::make();
            $names = $tree->getModuleNames();

            expect($names)->toBeArray()
                ->and($names)->toContain('myapp/core')
                ->and($names)->toContain('myapp/sale');
        });
    });

    describe('getDependencies', function (): void {
        it('returns dependencies for a module', function (): void {
            $tree = ModuleRegistry::make();
            $dependencies = $tree->getDependencies('myapp/sale');

            expect($dependencies)->toBeArray()
                ->and($dependencies)->toContain('myapp/core');
        });

        it('returns empty array for module without dependencies', function (): void {
            $tree = ModuleRegistry::make();
            $dependencies = $tree->getDependencies('myapp/kernel');

            // Core has no myapp/* dependencies (only external packages)
            expect($dependencies)->toBeArray()
                ->and($dependencies)->toBeEmpty();
        });

        it('returns empty array for unknown module', function (): void {
            $tree = ModuleRegistry::make();
            $dependencies = $tree->getDependencies('myapp/nonexistent');

            expect($dependencies)->toBeArray()
                ->and($dependencies)->toBeEmpty();
        });
    });

    describe('getDependencyGraph', function (): void {
        it('returns complete dependency graph', function (): void {
            $tree = ModuleRegistry::make();
            $graph = $tree->getDependencyGraph();

            expect($graph)->toBeArray()
                ->and($graph)->toHaveKey('myapp/core')
                ->and($graph)->toHaveKey('myapp/sale');
        });
    });

    describe('getTopologicalOrder', function (): void {
        it('returns modules in dependency order (dependencies first)', function (): void {
            $tree = ModuleRegistry::make();
            $order = $tree->getTopologicalOrder();

            expect($order)->toBeArray()
                ->and($order)->toContain('myapp/core')
                ->and($order)->toContain('myapp/sale');

            // Core should come before modules that depend on it
            $coreIndex = array_search('myapp/core', $order, true);
            $saleIndex = array_search('myapp/sale', $order, true);

            expect($coreIndex)->toBeLessThan($saleIndex);
        });

        it('places modules with no dependencies first', function (): void {
            $tree = ModuleRegistry::make();
            $order = $tree->getTopologicalOrder();

            // Core has no module dependencies, should be first
            expect($order[0])->toBe('myapp/kernel');
        });

        it('produces the exact dependency-first order for the fixtures', function (): void {
            // Pins the resolved order so the topological-sort engine cannot drift.
            expect(ModuleRegistry::make()->getTopologicalOrder())->toBe([
                'myapp/kernel',
                'myapp/core',
                'myapp/pim',
                'myapp/sale',
                'myapp/amazon',
            ]);
        });
    });

    describe('getReverseTopologicalOrder', function (): void {
        it('returns modules in reverse dependency order (dependents first)', function (): void {
            $tree = ModuleRegistry::make();
            $order = $tree->getReverseTopologicalOrder();

            expect($order)->toBeArray()
                ->and($order)->toContain('myapp/core')
                ->and($order)->toContain('myapp/sale');

            // Core should come AFTER modules that depend on it (reverse order)
            $coreIndex = array_search('myapp/core', $order, true);
            $saleIndex = array_search('myapp/sale', $order, true);

            expect($coreIndex)->toBeGreaterThan($saleIndex);
        });

        it('places modules with no dependencies last', function (): void {
            $tree = ModuleRegistry::make();
            $order = $tree->getReverseTopologicalOrder();

            // Core has no module dependencies, should be last
            expect(end($order))->toBe('myapp/kernel');
        });

        it('is the exact reverse of getTopologicalOrder', function (): void {
            $tree = ModuleRegistry::make();

            $topological = $tree->getTopologicalOrder();
            $reverse = $tree->getReverseTopologicalOrder();

            expect($reverse)->toBe(array_reverse($topological));
        });
    });

    describe('getDevDependencies', function (): void {
        it('returns the modules a module declares for its tests only', function (): void {
            $tree = ModuleRegistry::make();

            // The fixture core dev-requires sale, which requires core back: the shape
            // this whole feature exists for.
            expect($tree->getDevDependencies('myapp/core'))->toBe(['myapp/sale'])
                ->and($tree->getDevDependencies('myapp/sale'))->toBeEmpty();
        });

        it('keeps the dev edge out of the shipped dependencies', function (): void {
            $tree = ModuleRegistry::make();

            expect($tree->getDependencies('myapp/core'))->toBe(['myapp/kernel']);
        });
    });

    describe('getFullDependencyGraph', function (): void {
        it('carries the dev edges the shipped graph leaves out', function (): void {
            $tree = ModuleRegistry::make();

            expect($tree->getDependencyGraph()['myapp/core'])->toBe(['myapp/kernel'])
                ->and($tree->getFullDependencyGraph()['myapp/core'])->toBe(['myapp/kernel', 'myapp/sale']);
        });
    });

    describe('detectCircularDependencies', function (): void {
        it('returns empty array when no circular dependencies exist', function (): void {
            $tree = ModuleRegistry::make();
            $cycles = $tree->detectCircularDependencies();

            expect($cycles)->toBeArray()
                ->and($cycles)->toBeEmpty();
        });

        it('finds the cycle a require-dev edge closes when asked to include dev', function (): void {
            $tree = ModuleRegistry::make();
            $cycles = $tree->detectCircularDependencies(includeDev: true);

            expect($cycles)->not->toBeEmpty()
                ->and(array_merge(...$cycles))->toContain('myapp/core')
                ->and(array_merge(...$cycles))->toContain('myapp/sale');
        });

        it('leaves provider ordering alone, which is why the graphs stay apart', function (): void {
            // The regression this guards: folding require-dev into getDependencies()
            // would make this throw CircularDependencyException at boot.
            $tree = ModuleRegistry::make();

            expect($tree->getTopologicalOrder())->toContain('myapp/core')
                ->and($tree->detectCircularDependencies())->toBeEmpty();
        });
    });

    describe('getModulePath', function (): void {
        it('returns path for existing module', function (): void {
            $tree = ModuleRegistry::make();
            $path = $tree->getModulePath('myapp/core');

            expect($path)->not->toBeNull()
                ->and($path)->toContain('app-modules/core');
        });

        it('returns null for unknown module', function (): void {
            $tree = ModuleRegistry::make();
            $path = $tree->getModulePath('myapp/nonexistent');

            expect($path)->toBeNull();
        });
    });

    describe('clearCache', function (): void {
        it('clears internal cache', function (): void {
            $tree = ModuleRegistry::make();

            // Call twice to populate cache
            $modules1 = $tree->getAllModules();
            $tree->clearCache();
            $modules2 = $tree->getAllModules();

            // Both should return same data
            expect(array_keys($modules1))->toBe(array_keys($modules2));
        });
    });
});

describe('CircularDependencyException', function (): void {
    it('contains cycle information in message', function (): void {
        $cycles = [['myapp/a', 'myapp/b', 'myapp/a']];
        $exception = new CircularDependencyException($cycles);

        expect($exception->getMessage())->toContain('Circular dependencies detected')
            ->and($exception->getMessage())->toContain('myapp/a')
            ->and($exception->getMessage())->toContain('myapp/b')
            ->and($exception->cycles)->toBe($cycles);
    });
});
