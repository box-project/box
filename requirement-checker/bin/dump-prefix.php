<?php

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\RequirementChecker;

use RuntimeException;
use function file_put_contents;
use function sprintf;

// See https://github.com/easysoft/phpmicro for the micro SAPI.
if (false === in_array(PHP_SAPI, array('cli', 'phpdbg', 'embed', 'micro'), true)) {
    echo PHP_EOL.'The application may only be invoked from a command line, got "'.PHP_SAPI.'"'.PHP_EOL;

    exit(1);
}

function get_prefix(): string
{
    $lastRelease = shell_exec('git describe --abbrev=0 --tags HEAD');

    if (!is_string($lastRelease) || '' === $lastRelease) {
        throw new RuntimeException('Invalid tag name found.');
    }

    return 'HumbugBox'.str_replace('.', '', $lastRelease);
}

$prefixPath = __DIR__ . '/../dist/prefix';

error_clear_last();
$result = file_put_contents($prefixPath, get_prefix());

if (false !== $result) {
    return;
}

$error = error_get_last();

throw new RuntimeException(
    sprintf(
        'Could not write or create the file "%s": %s',
        $prefixPath,
        $error['message'],
    ),
    $error['type'] ?? 1
);
