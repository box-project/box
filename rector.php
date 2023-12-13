<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/vendor-bin/rector/vendor/autoload.php',
    ]);

    $rectorConfig->importNames();

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        PHPUnitSetList::PHPUNIT_100,
    ]);

    $rectorConfig->skip([
        \Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector::class,
        \Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector::class,
        \Rector\Php55\Rector\String_\StringClassNameToClassConstantRector::class => [
            __DIR__.'/src/Configuration/Configuration.php',
        ],
        \Rector\Php73\Rector\String_\SensitiveHereNowDocRector::class,
    ]);
};
