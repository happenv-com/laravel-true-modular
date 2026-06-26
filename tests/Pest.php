<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Absolute path to the fixture app-modules directory.
 */
function appModulesFixture(): string
{
    return __DIR__.'/fixtures/app-modules';
}
