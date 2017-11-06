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

namespace KevinGH\Box;

use Symfony\Component\Filesystem\Filesystem;

function escape_path(string $path): string
{
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
}

/**
 * Creates a temporary directory.
 *
 * @param string $namespace the directory path in the system's temporary
 *                          directory
 * @param string $className the name of the test class
 *
 * @return string the path to the created directory
 */
function make_tmp_dir(string $namespace, string $className): string
{
    if (false !== ($pos = strrpos($className, '\\'))) {
        $shortClass = substr($className, $pos + 1);
    } else {
        $shortClass = $className;
    }

    // Usage of realpath() is important if the temporary directory is a
    // symlink to another directory (e.g. /var => /private/var on some Macs)
    // We want to know the real path to avoid comparison failures with
    // code that uses real paths only
    $systemTempDir = str_replace('\\', '/', realpath(sys_get_temp_dir()));
    $basePath = $systemTempDir.'/'.$namespace.'/'.$shortClass;

    while (false === @mkdir($tempDir = escape_path($basePath.random_int(10000, 99999)), 0777, true)) {
        // Run until we are able to create a directory
    }

    return $tempDir;
}

//TODO: https://github.com/humbug/php-scoper/pull/19/files#r118838268
function remove_dir(string $path): void
{
    $path = escape_path($path);

    if (defined('PHP_WINDOWS_VERSION_BUILD')) {
        exec(sprintf('rd /s /q %s', escapeshellarg($path)));
    } else {
        (new Filesystem())->remove($path);
    }
}
