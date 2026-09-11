<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\NamespaceMatcher;

describe('longest prefix wins', function (): void {
    it('prefers the deeper of two namespaces that both prefix the class', function (): void {
        $map = [
            'Acme\\' => 'acme/root',
            'Acme\\Catalog\\' => 'acme/catalog',
        ];

        expect(NamespaceMatcher::longestPrefix('Acme\\Catalog\\Models\\Product', $map))->toBe('acme/catalog')
            ->and(NamespaceMatcher::longestPrefix('Acme\\Sale\\Models\\Order', $map))->toBe('acme/root');
    });

    it('does not require the prefix to end on a namespace boundary', function (): void {
        // The matcher is a string-prefix matcher and callers rely on that: the module
        // locator feeds it filesystem paths, and a segment-aware match would answer
        // differently for keys that do not end in a separator.
        expect(NamespaceMatcher::longestPrefix('AcmeCatalog\\Product', ['Acme' => 'acme/root']))->toBe('acme/root');
    });

    it('matches filesystem paths, whose separator is not a backslash', function (): void {
        $map = [
            '/app-modules/sale/' => 'acme/sale',
            '/app-modules/sale-reports/' => 'acme/sale-reports',
        ];

        expect(NamespaceMatcher::longestPrefix('/app-modules/sale-reports/src/Report.php', $map))
            ->toBe('acme/sale-reports');
    });
});

describe('no answer', function (): void {
    it('returns null when nothing prefixes the subject', function (): void {
        expect(NamespaceMatcher::longestPrefix('Other\\Thing', ['Acme\\' => 'acme/root']))->toBeNull();
    });

    it('returns null for an empty map', function (): void {
        expect(NamespaceMatcher::longestPrefix('Acme\\Thing', []))->toBeNull();
    });

    it('ignores keys longer than the subject', function (): void {
        expect(NamespaceMatcher::longestPrefix('Acme\\', ['Acme\\Catalog\\' => 'acme/catalog']))->toBeNull();
    });

    it('returns null for an empty subject unless an empty key is mapped', function (): void {
        expect(NamespaceMatcher::longestPrefix('', ['Acme\\' => 'acme/root']))->toBeNull()
            ->and(NamespaceMatcher::longestPrefix('', ['' => 'catch-all']))->toBe('catch-all');
    });

    it('distinguishes a mapped null value from no match', function (): void {
        expect(NamespaceMatcher::longestPrefix('Acme\\Thing', ['Acme\\' => null]))->toBeNull()
            ->and(NamespaceMatcher::longestPrefix('Acme\\Thing', ['Acme\\' => false]))->toBeFalse();
    });
});

describe('reusable index', function (): void {
    it('answers a built index exactly as the one-shot call does', function (): void {
        $map = [
            'Acme\\' => 'acme/root',
            'Acme\\Catalog\\' => 'acme/catalog',
            'Acme\\Catalog\\Database\\Factories\\' => 'acme/catalog',
            'Beta\\Sale\\' => 'beta/sale',
        ];

        $matcher = NamespaceMatcher::for($map);

        $subjects = [
            'Acme\\Catalog\\Database\\Factories\\ProductFactory',
            'Acme\\Catalog\\Models\\Product',
            'Acme\\Thing',
            'Beta\\Sale\\Models\\Order',
            'Gamma\\Nothing',
        ];

        foreach ($subjects as $subject) {
            expect($matcher->match($subject))->toBe(NamespaceMatcher::longestPrefix($subject, $map));
        }
    });
});
