<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\DowngradeLevelSetList;
use Rector\Set\ValueObject\LevelSetList;

// Common configuration
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->autoloadPaths([
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../vendor-bin/rector/vendor/autoload.php',
    ]);

    $rectorConfig->importNames();
};
