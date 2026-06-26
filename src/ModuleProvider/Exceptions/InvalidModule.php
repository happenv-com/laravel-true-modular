<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Exceptions;

use Exception;

final class InvalidModule extends Exception
{
    public static function nameIsRequired(): self
    {
        return new self('This module does not have a name. You can set one with `$module->name("yourName")`');
    }

    public static function nameRequiredFor(string $feature): self
    {
        return new self(sprintf('Call `$module->name(...)` before `%s()`; it is scoped by the module name.', $feature));
    }

    public static function configAlreadyRegistered(string $config): self
    {
        return new self(sprintf(
            'The config [%s] is already registered on this module. Configs are scoped per module, so each may be declared only once.',
            $config,
        ));
    }
}
