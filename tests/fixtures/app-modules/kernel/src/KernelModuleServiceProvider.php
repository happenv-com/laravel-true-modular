<?php

declare(strict_types=1);

namespace Myapp\Kernel;

use Illuminate\Support\ServiceProvider;

/**
 * Fixture provider for the `myapp/kernel` module — first in the fixture
 * topological order, since kernel declares no dependencies.
 */
final class KernelModuleServiceProvider extends ServiceProvider {}
