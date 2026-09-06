<?php

declare(strict_types=1);

namespace Myapp\Sale;

use Illuminate\Support\ServiceProvider;

/**
 * Fixture provider for the `myapp/sale` module — sorts after kernel, core and
 * pim, so registering it first forces the sorter to actually move something.
 */
final class SaleModuleServiceProvider extends ServiceProvider {}
