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

$expectedComposerBin = $argv[1];
$compilationLogPath = $argv[2];

if (!file_exists($compilationLogPath)) {
    exit(1);
}

$compilationLog = @file_get_contents($compilationLogPath);

if (false === $compilationLog) {
    exit(1);
}

$result = preg_match(
    "/\\? Dumping the Composer autoloader[\\n\\s]+> '(?<composerBin>[\\/\\p{L}\\d]+composer\\.phar)'/",
    $compilationLog,
    $matches,
);

if (1 !== $result) {
    exit(1);
}

$actualComposerBin = $matches['composerBin'];

exit($expectedComposerBin === $actualComposerBin ? 0 : 1);
