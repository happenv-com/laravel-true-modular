<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Module;
use InvalidArgumentException;

/**
 * @mixin Module
 */
trait HasPermissions
{
    /**
     * @var class-string[]
     */
    public array $permissions = [];

    /**
     * @param  class-string[]|class-string  $class
     *
     * @throws InvalidArgumentException
     */
    public function hasPermissions(array|string $class): static
    {
        if (is_array($class)) {
            foreach ($class as $permission) {
                $this->hasPermissions($permission);
            }

            return $this;
        }

        $this->permissions[] = $class;

        return $this;
    }
}
