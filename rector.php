<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withAutoloadPaths([
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/vendor-bin/rector/vendor/autoload.php',
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withPhpSets(php82: true)
    ->withAttributesSets(phpunit: true)
    ->withSkip([
        \Rector\Php55\Rector\String_\StringClassNameToClassConstantRector::class => [
            __DIR__.'/src/Configuration/Configuration.php',
        ],
        \Rector\Php73\Rector\String_\SensitiveHereNowDocRector::class,
    ]);
