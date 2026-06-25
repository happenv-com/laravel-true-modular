<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
    ])
    ->withComposerBased(laravel: true, phpunit: true)
    ->withPhpVersion(Rector\ValueObject\PhpVersion::PHP_83)
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
