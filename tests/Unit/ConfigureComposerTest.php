<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Setup\Steps\ConfigureComposer;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->files = new Filesystem;
    $this->base = sys_get_temp_dir().'/tm-composer-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->base);

    $this->writeComposer = function (array $require): void {
        $this->files->put($this->base.'/composer.json', json_encode([
            'name' => 'acme/app',
            'require' => $require,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    };

    $this->wire = fn (string $package) => (new ConfigureComposer($this->files, $this->base))
        ->wire('app-modules', $package);

    $this->require = fn (): array => json_decode($this->files->get($this->base.'/composer.json'), associative: true)['require'];
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->base);
});

it('inserts the requirement where sort-packages keeps it rather than appending', function (): void {
    ($this->writeComposer)([
        'php' => '^8.4',
        'ext-json' => '*',
        'acme/alpha' => '^1.0',
        'acme/zulu' => '^1.0',
    ]);

    ($this->wire)('acme/mike');

    expect(array_keys(($this->require)()))->toBe([
        'php',
        'ext-json',
        'acme/alpha',
        'acme/mike',
        'acme/zulu',
    ]);
});

it('keeps platform requirements ahead of the new package regardless of alphabet', function (): void {
    ($this->writeComposer)(['php' => '^8.4', 'ext-json' => '*']);

    ($this->wire)('acme/alpha');

    expect(array_keys(($this->require)()))->toBe(['php', 'ext-json', 'acme/alpha']);
});

it('appends when the new package sorts after everything present', function (): void {
    ($this->writeComposer)(['php' => '^8.4', 'acme/alpha' => '^1.0']);

    ($this->wire)('acme/zulu');

    expect(array_keys(($this->require)()))->toBe(['php', 'acme/alpha', 'acme/zulu']);
});

it('leaves a hand-ordered require block otherwise untouched', function (): void {
    ($this->writeComposer)(['zeta/one' => '^1.0', 'alpha/two' => '^1.0']);

    ($this->wire)('mike/three');

    // Only the insertion point is chosen; the existing (unsorted) order survives.
    expect(array_keys(($this->require)()))->toBe(['mike/three', 'zeta/one', 'alpha/two']);
});

it('updates the constraint in place when the package is already required', function (): void {
    ($this->writeComposer)(['php' => '^8.4', 'acme/alpha' => '^1.0', 'acme/zulu' => '^1.0']);

    ($this->wire)('acme/zulu');

    expect(($this->require)())->toBe([
        'php' => '^8.4',
        'acme/alpha' => '^1.0',
        'acme/zulu' => '1.0.0',
    ]);
});

it('prepends the path repository exactly once', function (): void {
    ($this->writeComposer)(['php' => '^8.4']);

    ($this->wire)('acme/alpha');
    ($this->wire)('acme/bravo');

    $repositories = json_decode($this->files->get($this->base.'/composer.json'), associative: true)['repositories'];

    expect($repositories)->toBe([['type' => 'path', 'url' => 'app-modules/*']]);
});
