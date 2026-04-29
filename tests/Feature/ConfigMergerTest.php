<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Config\ConfigMerger;

describe('ConfigMerger', function (): void {
    beforeEach(function (): void {
        $this->merger = new ConfigMerger;
    });

    describe('new keys', function (): void {
        it('adds keys that do not exist in original', function (): void {
            $result = $this->merger->merge(
                ['existing' => 'value'],
                ['new_key' => 'new_value'],
            );

            expect($result)->toBe(['existing' => 'value', 'new_key' => 'new_value']);
        });

        it('adds nested arrays that do not exist in original', function (): void {
            $result = $this->merger->merge(
                [],
                ['database' => ['host' => 'localhost', 'port' => 3306]],
            );

            expect($result)->toBe(['database' => ['host' => 'localhost', 'port' => 3306]]);
        });
    });

    describe('scalar values without overwrite', function (): void {
        it('keeps original scalar when overwrite is false', function (): void {
            $result = $this->merger->merge(
                ['path' => 'foo'],
                ['path' => 'bar'],
                overwrite: false,
            );

            expect($result)->toBe(['path' => 'foo']);
        });

        it('keeps original integer scalar when overwrite is false', function (): void {
            $result = $this->merger->merge(
                ['timeout' => 30],
                ['timeout' => 60],
                overwrite: false,
            );

            expect($result)->toBe(['timeout' => 30]);
        });

        it('keeps original boolean scalar when overwrite is false', function (): void {
            $result = $this->merger->merge(
                ['debug' => false],
                ['debug' => true],
                overwrite: false,
            );

            expect($result)->toBe(['debug' => false]);
        });

        it('keeps original null scalar when overwrite is false', function (): void {
            $result = $this->merger->merge(
                ['driver' => null],
                ['driver' => 'redis'],
                overwrite: false,
            );

            expect($result)->toBe(['driver' => null]);
        });
    });

    describe('scalar values with overwrite', function (): void {
        it('overwrites scalar when overwrite is true', function (): void {
            $result = $this->merger->merge(
                ['path' => 'foo'],
                ['path' => 'bar'],
                overwrite: true,
            );

            expect($result)->toBe(['path' => 'bar']);
        });

        it('overwrites integer scalar when overwrite is true', function (): void {
            $result = $this->merger->merge(
                ['timeout' => 30],
                ['timeout' => 60],
                overwrite: true,
            );

            expect($result)->toBe(['timeout' => 60]);
        });

        it('overwrites boolean scalar when overwrite is true', function (): void {
            $result = $this->merger->merge(
                ['debug' => false],
                ['debug' => true],
                overwrite: true,
            );

            expect($result)->toBe(['debug' => true]);
        });
    });

    describe('list arrays', function (): void {
        it('merges list arrays and deduplicates values', function (): void {
            $result = $this->merger->merge(
                ['providers' => ['App\\FooProvider', 'App\\BarProvider']],
                ['providers' => ['App\\BarProvider', 'App\\BazProvider']],
            );

            expect($result['providers'])->toBe(['App\\FooProvider', 'App\\BarProvider', 'App\\BazProvider']);
        });

        it('merges list arrays without duplicates', function (): void {
            $result = $this->merger->merge(
                ['paths' => ['/foo', '/bar']],
                ['paths' => ['/baz']],
            );

            expect($result['paths'])->toBe(['/foo', '/bar', '/baz']);
        });

        it('merges list arrays with integer values', function (): void {
            $result = $this->merger->merge(
                ['ports' => [80, 443]],
                ['ports' => [443, 8080]],
            );

            expect($result['ports'])->toBe([80, 443, 8080]);
        });

        it('preserves order with original first', function (): void {
            $result = $this->merger->merge(
                ['items' => ['a', 'b']],
                ['items' => ['c', 'd']],
            );

            expect($result['items'])->toBe(['a', 'b', 'c', 'd']);
        });

        it('reindexes after dedup', function (): void {
            $result = $this->merger->merge(
                ['items' => ['a', 'b', 'c']],
                ['items' => ['b', 'c', 'd']],
            );

            expect($result['items'])->toBe(['a', 'b', 'c', 'd']);
            expect($result['items'])
                ->toBeList();
        });

        it('merges lists containing arrays without array to string conversion', function (): void {
            $result = $this->merger->merge(
                ['servers' => [
                    ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100],
                ]],
                ['servers' => [
                    ['host' => '10.0.0.1', 'port' => 11211, 'weight' => 50],
                ]],
            );

            expect($result['servers'])->toBe([
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100],
                ['host' => '10.0.0.1', 'port' => 11211, 'weight' => 50],
            ]);
        });

        it('deduplicates identical arrays in lists', function (): void {
            $result = $this->merger->merge(
                ['servers' => [
                    ['host' => '127.0.0.1', 'port' => 11211],
                ]],
                ['servers' => [
                    ['host' => '127.0.0.1', 'port' => 11211],
                    ['host' => '10.0.0.1', 'port' => 11211],
                ]],
            );

            expect($result['servers'])->toBe([
                ['host' => '127.0.0.1', 'port' => 11211],
                ['host' => '10.0.0.1', 'port' => 11211],
            ]);
        });
    });

    describe('associative arrays (recursive)', function (): void {
        it('recursively merges associative arrays', function (): void {
            $result = $this->merger->merge(
                ['database' => ['host' => 'localhost', 'port' => 3306]],
                ['database' => ['host' => 'remote', 'name' => 'mydb']],
                overwrite: false,
            );

            expect($result)->toBe([
                'database' => ['host' => 'localhost', 'name' => 'mydb', 'port' => 3306],
            ]);
        });

        it('recursively merges with overwrite', function (): void {
            $result = $this->merger->merge(
                ['database' => ['host' => 'localhost', 'port' => 3306]],
                ['database' => ['host' => 'remote', 'name' => 'mydb']],
                overwrite: true,
            );

            expect($result)->toBe([
                'database' => ['host' => 'remote', 'name' => 'mydb', 'port' => 3306],
            ]);
        });

        it('handles deeply nested associative arrays', function (): void {
            $result = $this->merger->merge(
                ['level1' => ['level2' => ['level3' => 'original']]],
                ['level1' => ['level2' => ['extra' => 'value', 'level3' => 'new']]],
                overwrite: false,
            );

            expect($result)->toBe([
                'level1' => ['level2' => ['extra' => 'value', 'level3' => 'original']],
            ]);
        });
    });

    describe('mixed scenarios', function (): void {
        it('handles mix of scalars, lists and associative arrays', function (): void {
            $original = [
                'app_name' => 'Myapp',
                'database' => [
                    'connections' => ['mysql', 'redis'],
                    'host' => 'localhost',
                ],
                'providers' => ['App\\CoreProvider'],
            ];

            $extending = [
                'app_name' => 'Overridden',
                'database' => [
                    'connections' => ['redis', 'pgsql'],
                    'host' => 'remote',
                    'port' => 5432,
                ],
                'new_section' => true,
                'providers' => ['App\\CoreProvider', 'App\\SaleProvider'],
            ];

            $result = $this->merger->merge($original, $extending, overwrite: false);

            expect($result['app_name'])->toBe('Myapp')
                ->and($result['providers'])->toBe(['App\\CoreProvider', 'App\\SaleProvider'])
                ->and($result['database']['host'])->toBe('localhost')
                ->and($result['database']['connections'])->toBe(['mysql', 'redis', 'pgsql'])
                ->and($result['database']['port'])->toBe(5432)
                ->and($result['new_section'])->toBeTrue();
        });

        it('handles mix of scalars, lists and associative arrays with overwrite', function (): void {
            $original = [
                'app_name' => 'Myapp',
                'database' => ['host' => 'localhost'],
            ];

            $extending = [
                'app_name' => 'Overridden',
                'database' => ['host' => 'remote'],
            ];

            $result = $this->merger->merge($original, $extending, overwrite: true);

            expect($result['app_name'])->toBe('Overridden')
                ->and($result['database']['host'])->toBe('remote');
        });
    });

    describe('edge cases', function (): void {
        it('returns original when extending is empty', function (): void {
            $result = $this->merger->merge(['foo' => 'bar'], []);

            expect($result)->toBe(['foo' => 'bar']);
        });

        it('returns extending when original is empty', function (): void {
            $result = $this->merger->merge([], ['foo' => 'bar']);

            expect($result)->toBe(['foo' => 'bar']);
        });

        it('returns empty array when both are empty', function (): void {
            $result = $this->merger->merge([], []);

            expect($result)
                ->toBeEmpty();
        });

        it('does not turn scalar into array when extending has array', function (): void {
            $result = $this->merger->merge(
                ['key' => 'scalar'],
                ['key' => ['array', 'value']],
                overwrite: false,
            );

            expect($result['key'])->toBe('scalar');
        });

        it('overwrites scalar with array when overwrite is true', function (): void {
            $result = $this->merger->merge(
                ['key' => 'scalar'],
                ['key' => ['array', 'value']],
                overwrite: true,
            );

            expect($result['key'])->toBe(['array', 'value']);
        });

        it('does not turn array into scalar when extending has scalar', function (): void {
            $result = $this->merger->merge(
                ['key' => ['array', 'value']],
                ['key' => 'scalar'],
                overwrite: false,
            );

            expect($result['key'])->toBe(['array', 'value']);
        });

        it('overwrites array with scalar when overwrite is true', function (): void {
            $result = $this->merger->merge(
                ['key' => ['array', 'value']],
                ['key' => 'scalar'],
                overwrite: true,
            );

            expect($result['key'])->toBe('scalar');
        });

        it('handles empty list merged with non-empty list', function (): void {
            $result = $this->merger->merge(
                ['items' => []],
                ['items' => ['a', 'b']],
            );

            expect($result['items'])->toBe(['a', 'b']);
        });

        it('defaults overwrite to false', function (): void {
            $result = $this->merger->merge(
                ['key' => 'original'],
                ['key' => 'new'],
            );

            expect($result['key'])->toBe('original');
        });
    });
});
