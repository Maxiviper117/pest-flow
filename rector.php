<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
    ])
    ->withPhpSets()
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
    ])
    ->withPreparedSets(
        codeQuality: true,
        deadCode: true,
        earlyReturn: true,
        typeDeclarations: true,
        privatization: true,
    );
