<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\DowngradeLevelSetList;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../vendor-bin/rector/vendor/autoload.php',
    ]);

    $rectorConfig->importNames();

    $rectorConfig->sets([
        // TODO
        // DowngradeLevelSetList::DOWN_TO_PHP_72,
    ]);
};
