<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleProvider\Module;

const FOO_CHECK = 'Acme\\Health\\FooCheck';
const BAR_CHECK = 'Acme\\Health\\BarCheck';

it('declares health checks via spread or a single array', function (): void {
    $spread = (new Module)->hasHealthChecks(FOO_CHECK, BAR_CHECK);
    expect($spread->healthChecks)->toBe([FOO_CHECK, BAR_CHECK]);

    $array = (new Module)->hasHealthChecks([FOO_CHECK, BAR_CHECK]);
    expect($array->healthChecks)->toBe([FOO_CHECK, BAR_CHECK]);
});

it('appends a single health check and defaults to empty', function (): void {
    expect((new Module)->healthChecks)->toBe([]);

    $module = (new Module)->hasHealthCheck(FOO_CHECK)->hasHealthCheck(BAR_CHECK);
    expect($module->healthChecks)->toBe([FOO_CHECK, BAR_CHECK]);
});
