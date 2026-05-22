<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem\Exceptions;

use Exception;

final class CircularDependencyException extends Exception
{
    /**
     * @param  array<array<string>>  $cycles
     */
    public function __construct(
        public readonly array $cycles,
    ) {
        $cycleDescriptions = array_map(
            static fn (array $cycle): string => implode(' -> ', $cycle),
            $cycles
        );

        $message = 'Circular dependencies detected: '.implode('; ', $cycleDescriptions);

        parent::__construct($message);
    }
}
