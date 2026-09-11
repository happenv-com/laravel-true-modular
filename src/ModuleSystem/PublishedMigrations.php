<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Safe\Exceptions\FilesystemException;

use function Safe\glob;

/**
 * The migrations already published into the application's own `database/migrations`.
 *
 * Migration discovery asks the same question — "has this module migration already been
 * published, and under what name?" — once per migration file, and every module asks it
 * about the same directory. Read straight from disk that is one `glob()` per migration
 * file: on a host with ~650 module migrations it was the single most expensive thing in
 * the whole application boot, and every one of those calls returned the same listing.
 *
 * Resolved from the container, so the listing lives exactly as long as the application
 * instance that read it. A process-wide cache would be wrong for the one case where the
 * directory changes underneath us: a test that publishes migrations and then boots a
 * second application would go on seeing the directory as it was before the publish.
 */
final class PublishedMigrations
{
    /** @var array<string, list<string>> */
    private array $listings = [];

    /**
     * Paths of the already-published migrations matching a glob pattern.
     *
     * @return list<string>
     *
     * @throws FilesystemException
     */
    public function matching(string $pattern): array
    {
        return $this->listings[$pattern] ??= glob($pattern);
    }
}
