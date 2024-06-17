<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;

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
    ->withSets([
        PHPUnitSetList::PHPUNIT_100,
    ])
    ->withSkip([
        \Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector::class,
        \Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector::class,
        \Rector\Php55\Rector\String_\StringClassNameToClassConstantRector::class => [
            __DIR__.'/src/Configuration/Configuration.php',
        ],
        \Rector\Php73\Rector\String_\SensitiveHereNowDocRector::class,
    ]);
