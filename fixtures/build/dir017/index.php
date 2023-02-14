<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Composer\InstalledVersions;
use function iter\toIter;

require __DIR__.'/vendor/autoload.php';

$packages = toIter([
    'nikic/iter',
    'phpstan/extension-installer',
]);

foreach ($packages as $package) {
    echo sprintf(
        'The package "%s" is %sinstalled.'.PHP_EOL,
        $package,
        InstalledVersions::isInstalled($package) ? '' : 'NOT ',
    );
}
