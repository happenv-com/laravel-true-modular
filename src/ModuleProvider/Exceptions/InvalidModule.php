<?php

namespace Happenv\LaravelTrueModular\ModuleProvider\Exceptions;

use Exception;

final class InvalidModule extends Exception
{
    public static function nameIsRequired(): self
    {
        return new self('This module does not have a name. You can set one with `$module->name("yourName")`');
    }
}
