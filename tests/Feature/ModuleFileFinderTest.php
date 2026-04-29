<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleFileFinder;
use Myapp\Sale\Database\Seeders\CustomerSeeder;
use Myapp\Sale\Database\Seeders\OrderSeeder;
use Illuminate\Support\Collection;

describe('ModuleFileFinder', function (): void {
    describe('findFiles', function (): void {
        it('finds files in module directories in dependency order', function (): void {
            $finder = ModuleFileFinder::make();
            $files = $finder->findFiles('database/seeders');

            expect($files)->toBeInstanceOf(Collection::class)
                ->and($files->count())->toBeGreaterThan(0);

            // Verify structure
            $first = $files->first();
            expect($first)->toHaveKey('file')
                ->and($first)->toHaveKey('module');
        });

        it('respects module dependency order', function (): void {
            $finder = ModuleFileFinder::make();
            $files = $finder->findFiles('database/seeders');

            $modules = $files->pluck('module')->unique()->values()->toArray();

            // If both pim and sale have seeders, pim should come first (sale depends on pim)
            if (in_array('myapp/pim', $modules, true) && in_array('myapp/sale', $modules, true)) {
                $pimIndex = array_search('myapp/pim', $modules, true);
                $saleIndex = array_search('myapp/sale', $modules, true);

                expect($pimIndex)->toBeLessThan($saleIndex);
            }
        });

        it('returns empty collection for non-existent directory', function (): void {
            $finder = ModuleFileFinder::make();
            $files = $finder->findFiles('nonexistent/directory');

            expect($files)->toBeInstanceOf(Collection::class)
                ->and($files)->toBeEmpty();
        });
    });

    describe('findFilesReverse', function (): void {
        it('finds files in reverse dependency order', function (): void {
            $finder = ModuleFileFinder::make();
            $files = $finder->findFilesReverse('database/seeders');

            expect($files)->toBeInstanceOf(Collection::class);

            $modules = $files->pluck('module')->unique()->values()->toArray();

            // In reverse order, dependent modules come first
            if (in_array('myapp/pim', $modules, true) && in_array('myapp/sale', $modules, true)) {
                $pimIndex = array_search('myapp/pim', $modules, true);
                $saleIndex = array_search('myapp/sale', $modules, true);

                // Sale should come before pim (reverse order)
                expect($saleIndex)->toBeLessThan($pimIndex);
            }
        });
    });

    describe('findClasses', function (): void {
        it('finds classes with proper namespace resolution', function (): void {
            $finder = ModuleFileFinder::make();
            $classes = $finder->findClasses('database/seeders', 'Database\Seeders');

            expect($classes)->toBeInstanceOf(Collection::class)
                ->and($classes->count())->toBeGreaterThan(0);

            // Verify structure
            $first = $classes->first();
            expect($first)->toHaveKey('class')
                ->and($first)->toHaveKey('module')
                ->and($first)->toHaveKey('file')
                ->and(class_exists($first['class']))->toBeTrue();
        });

        it('returns seeder classes for sale module', function (): void {
            $finder = ModuleFileFinder::make();
            $classes = $finder->findClasses('database/seeders', 'Database\Seeders');

            $saleClasses = $classes
                ->filter(fn (array $item): bool => $item['module'] === 'myapp/sale')
                ->pluck('class')
                ->toArray();

            expect($saleClasses)->toContain(OrderSeeder::class)
                ->and($saleClasses)->toContain(CustomerSeeder::class);
        });
    });

    describe('findClassesReverse', function (): void {
        it('finds classes in reverse dependency order', function (): void {
            $finder = ModuleFileFinder::make();
            $classes = $finder->findClassesReverse('database/seeders', 'Database\Seeders');

            expect($classes)->toBeInstanceOf(Collection::class);

            $modules = $classes->pluck('module')->unique()->values()->toArray();

            // In reverse order, dependent modules come first
            if (in_array('myapp/pim', $modules, true) && in_array('myapp/sale', $modules, true)) {
                $pimIndex = array_search('myapp/pim', $modules, true);
                $saleIndex = array_search('myapp/sale', $modules, true);

                expect($saleIndex)->toBeLessThan($pimIndex);
            }
        });
    });

    describe('findFilesGroupedByModule', function (): void {
        it('groups files by module name', function (): void {
            $finder = ModuleFileFinder::make();
            $grouped = $finder->findFilesGroupedByModule('database/seeders');

            expect($grouped)->toBeInstanceOf(Collection::class)
                ->and($grouped)->toHaveKey('myapp/sale');

            $saleFiles = $grouped->get('myapp/sale');
            expect($saleFiles)->toBeArray()
                ->and(count($saleFiles))->toBeGreaterThan(0);
        });

        it('only includes modules with files', function (): void {
            $finder = ModuleFileFinder::make();
            $grouped = $finder->findFilesGroupedByModule('database/seeders');

            // Core has no seeders, should not be in the result
            expect($grouped)->not->toHaveKey('myapp/kernel');
        });

        it('maintains dependency order in collection keys', function (): void {
            $finder = ModuleFileFinder::make();
            $grouped = $finder->findFilesGroupedByModule('database/seeders');

            $modules = $grouped->keys()->all();

            if (in_array('myapp/pim', $modules, true) && in_array('myapp/sale', $modules, true)) {
                $pimIndex = array_search('myapp/pim', $modules, true);
                $saleIndex = array_search('myapp/sale', $modules, true);

                expect($pimIndex)->toBeLessThan($saleIndex);
            }
        });
    });
});
