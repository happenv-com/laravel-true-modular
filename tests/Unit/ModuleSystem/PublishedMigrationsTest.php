<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\PublishedMigrations;

function publishedMigrationsFixtureDir(): string
{
    $dir = sys_get_temp_dir().'/true-modular-published-'.bin2hex(random_bytes(6));
    mkdir($dir, recursive: true);
    file_put_contents($dir.'/2026_01_01_000000_create_customers_table.php', '<?php');

    return $dir;
}

it('lists the migrations matching a pattern', function (): void {
    $dir = publishedMigrationsFixtureDir();

    expect((new PublishedMigrations)->matching($dir.'/*.php'))
        ->toBe([$dir.'/2026_01_01_000000_create_customers_table.php']);
});

it('reads a directory once, however many migrations ask about it', function (): void {
    // The whole reason this object exists: discovery asks per migration file, and every
    // module asks about the same directory. A second read would put back the ~650 glob
    // calls per boot it was introduced to remove.
    $dir = publishedMigrationsFixtureDir();
    $index = new PublishedMigrations;

    $first = $index->matching($dir.'/*.php');
    file_put_contents($dir.'/2026_02_02_000000_create_orders_table.php', '<?php');

    expect($index->matching($dir.'/*.php'))->toBe($first);
});

it('keeps distinct patterns apart', function (): void {
    $dir = publishedMigrationsFixtureDir();
    file_put_contents($dir.'/notes.txt', 'x');
    $index = new PublishedMigrations;

    expect($index->matching($dir.'/*.php'))->toHaveCount(1)
        ->and($index->matching($dir.'/*.txt'))->toBe([$dir.'/notes.txt']);
});

it('answers with an empty list when nothing matches', function (): void {
    expect((new PublishedMigrations)->matching(publishedMigrationsFixtureDir().'/*.stub'))->toBe([]);
});
