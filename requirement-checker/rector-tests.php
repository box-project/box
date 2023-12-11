<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;

$applyCommonConfig = require __DIR__.'/rector.php';

return static function (RectorConfig $rectorConfig) use ($applyCommonConfig): void {
    $applyCommonConfig($rectorConfig);

    $rectorConfig->paths([
        __DIR__ . '/tests',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        PHPUnitSetList::PHPUNIT_100,
    ]);

    $rectorConfig->skip([
        ClosureToArrowFunctionRector::class
    ]);
};
