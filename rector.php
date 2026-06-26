<?php

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
    ])
    ->withComposerBased(laravel: true, phpunit: true)
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        namedArgs: true,
        instanceOf: true,
        earlyReturn: true,
    )
    ->withPhpSets();
