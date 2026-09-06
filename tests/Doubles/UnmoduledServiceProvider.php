<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Tests\Doubles;

use Illuminate\Support\ServiceProvider;

/**
 * A provider belonging to no module — the sorter's "other providers" bucket,
 * which is what every framework and vendor provider falls into.
 */
final class UnmoduledServiceProvider extends ServiceProvider {}
