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

use Isolated\Symfony\Component\Finder\Finder as IsolatedFinder;

$jetBrainStubs = (static function (): array {
    $files = [];

    foreach (new DirectoryIterator(__DIR__.'/../vendor/jetbrains/phpstorm-stubs') as $directoryInfo) {
        if ($directoryInfo->isDot()) {
            continue;
        }

        if (false === $directoryInfo->isDir()) {
            continue;
        }

        if (in_array($directoryInfo->getBasename(), ['tests', 'meta'], true)) {
            continue;
        }

        foreach (new DirectoryIterator($directoryInfo->getPathName()) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            if (1 !== preg_match('/\.php$/', $fileInfo->getBasename())) {
                continue;
            }

            $files[] = $fileInfo->getPathName();
        }
    }

    return $files;
})();

return [...$jetBrainStubs];
