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

use Assert\Assertion;
use Phar;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

function canonicalize(string $path): string
{
    $lastChar = substr($path, -1);

    $canonical = Path::canonicalize($path);

    return '/' === $lastChar ? $canonical.$lastChar : $canonical;
}

function is_absolute(string $path): bool
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new Filesystem();
    }

    return $fileSystem->isAbsolutePath($path);
}

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

/**
 * TODO: this function should be pushed down to the PHAR extension.
 */
function get_phar_compression_algorithms(): array
{
    static $algorithms = [
        'GZ' => Phar::GZ,
        'BZ2' => Phar::BZ2,
        'NONE' => Phar::NONE,
    ];

    return $algorithms;
}

function formatted_filesize(string $path)
{
    Assertion::file($path);

    $size = filesize($path);
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $power = $size > 0 ? floor(log($size, 1024)) : 0;

    return sprintf(
        '%s%s',
        number_format(
            $size / (1024 ** $power),
            2,
            '.',
            ','
        ),
        $units[$power]
    );
}

function register_aliases(): void
{
    if (false === class_exists(\Herrera\Box\Compactor\Javascript::class, false)) {
        class_alias(\KevinGH\Box\Compactor\Javascript::class, \Herrera\Box\Compactor\Javascript::class);
    }

    if (false === class_exists(\Herrera\Box\Compactor\Json::class, false)) {
        class_alias(\KevinGH\Box\Compactor\Json::class, \Herrera\Box\Compactor\Json::class);
    }

    if (false === class_exists(\Herrera\Box\Compactor\Php::class, false)) {
        class_alias(\KevinGH\Box\Compactor\Php::class, \Herrera\Box\Compactor\Php::class);
    }
}
