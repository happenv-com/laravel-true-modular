<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\Exceptions\CircularDependencyException;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleTree;

describe('ModuleTree', function (): void {
    describe('getAllModules', function (): void {
        it('discovers all modules in app-modules directory', function (): void {
            $tree = ModuleTree::make();
            $modules = $tree->getAllModules();

            expect($modules)->toBeArray()
                ->and($modules)->toHaveKey('myapp/core')
                ->and($modules)->toHaveKey('myapp/sale')
                ->and($modules)->toHaveKey('myapp/pim');
        });

        it('returns module data with name, path, and composer', function (): void {
            $tree = ModuleTree::make();
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
            $tree = ModuleTree::make();
            $names = $tree->getModuleNames();

            expect($names)->toBeArray()
                ->and($names)->toContain('myapp/core')
                ->and($names)->toContain('myapp/sale');
        });
    });

    describe('getDependencies', function (): void {
        it('returns dependencies for a module', function (): void {
            $tree = ModuleTree::make();
            $dependencies = $tree->getDependencies('myapp/sale');

            expect($dependencies)->toBeArray()
                ->and($dependencies)->toContain('myapp/core');
        });

        it('returns empty array for module without dependencies', function (): void {
            $tree = ModuleTree::make();
            $dependencies = $tree->getDependencies('myapp/kernel');

            // Core has no myapp/* dependencies (only external packages)
            expect($dependencies)->toBeArray()
                ->and($dependencies)->toBeEmpty();
        });

        it('returns empty array for unknown module', function (): void {
            $tree = ModuleTree::make();
            $dependencies = $tree->getDependencies('myapp/nonexistent');

            expect($dependencies)->toBeArray()
                ->and($dependencies)->toBeEmpty();
        });
    });

    describe('getDependencyGraph', function (): void {
        it('returns complete dependency graph', function (): void {
            $tree = ModuleTree::make();
            $graph = $tree->getDependencyGraph();

            expect($graph)->toBeArray()
                ->and($graph)->toHaveKey('myapp/core')
                ->and($graph)->toHaveKey('myapp/sale');
        });
    });

    describe('getTopologicalOrder', function (): void {
        it('returns modules in dependency order (dependencies first)', function (): void {
            $tree = ModuleTree::make();
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
            $tree = ModuleTree::make();
            $order = $tree->getTopologicalOrder();

            // Core has no module dependencies, should be first
            expect($order[0])->toBe('myapp/kernel');
        });
    });

    describe('getReverseTopologicalOrder', function (): void {
        it('returns modules in reverse dependency order (dependents first)', function (): void {
            $tree = ModuleTree::make();
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
            $tree = ModuleTree::make();
            $order = $tree->getReverseTopologicalOrder();

            // Core has no module dependencies, should be last
            expect(end($order))->toBe('myapp/kernel');
        });

        it('is the exact reverse of getTopologicalOrder', function (): void {
            $tree = ModuleTree::make();

            $topological = $tree->getTopologicalOrder();
            $reverse = $tree->getReverseTopologicalOrder();

            expect($reverse)->toBe(array_reverse($topological));
        });
    });

    describe('detectCircularDependencies', function (): void {
        it('returns empty array when no circular dependencies exist', function (): void {
            $tree = ModuleTree::make();
            $cycles = $tree->detectCircularDependencies();

            expect($cycles)->toBeArray()
                ->and($cycles)->toBeEmpty();
        });
    });

    describe('getModulePath', function (): void {
        it('returns path for existing module', function (): void {
            $tree = ModuleTree::make();
            $path = $tree->getModulePath('myapp/core');

            expect($path)->not->toBeNull()
                ->and($path)->toContain('app-modules/core');
        });

        it('returns null for unknown module', function (): void {
            $tree = ModuleTree::make();
            $path = $tree->getModulePath('myapp/nonexistent');

            expect($path)->toBeNull();
        });
    });

    describe('clearCache', function (): void {
        it('clears internal cache', function (): void {
            $tree = ModuleTree::make();

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
