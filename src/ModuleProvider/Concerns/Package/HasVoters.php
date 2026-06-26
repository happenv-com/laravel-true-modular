<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use InvalidArgumentException;

/**
 * @mixin Module
 */
trait HasVoters
{
    /**
     * @var class-string[]
     */
    public array $voters = [];

    /**
     * @param  class-string[]|class-string  $class
     *
     * @throws InvalidArgumentException
     */
    public function hasVoters(array|string $class): static
    {
        if (is_array($class)) {
            foreach ($class as $voter) {
                $this->hasVoters($voter);
            }

            return $this;
        }

        $this->voters[] = $class;

        return $this;
    }
}
