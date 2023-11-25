<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\DowngradeLevelSetList;
use Rector\Set\ValueObject\LevelSetList;

$applyCommonConfig = require __DIR__.'/rector.php';

return static function (RectorConfig $rectorConfig) use ($applyCommonConfig): void {
    $applyCommonConfig($rectorConfig);

    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_72,
        DowngradeLevelSetList::DOWN_TO_PHP_72,
    ]);
};
