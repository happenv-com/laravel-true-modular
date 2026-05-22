<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModelExtension;

use Illuminate\Database\Eloquent\Model;

// @codeCoverageIgnoreStart
abstract class ModelExtension
{
    public function __construct(protected Model $model) {}
}

// @codeCoverageIgnoreEnd
