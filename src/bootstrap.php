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

use ErrorException;
use RuntimeException;
use function bin2hex;
use function copy;
use function defined;
use function dirname;
use function random_bytes;
use function register_shutdown_function;
use function substr;
use function sys_get_temp_dir;
use function unlink;

// TODO: update PHP-Scoper to get rid of this horrible hack at some point
$findPhpScoperFunctions = function (): void {
    if (file_exists($autoload = __DIR__.'/../../php-scoper/src/functions.php')) {
        // Is installed via Composer
        require_once $autoload;

        return;
    }

    if (file_exists($autoload = __DIR__.'/../vendor/humbug/php-scoper/src/functions.php')) {
        // Is scoped (in PHAR or dumped directory) or is installed locally
        require_once $autoload;

        return;
    }

    if ('phar://' === substr(__FILE__, 0, 7)) {
        // Is in the PHAR but the PHAR has been renamed without the extension `.phar`. As a result the PHAR protocol
        // `phar://path/to/file/in/PHAR` will not work.
        // See https://github.com/amphp/parallel/commit/732694688461936bec02c0ccf020dfee10c4f7ee
        if (defined('PHAR_COPY')) {
            return;
        }

        $pharPath = dirname(substr(__FILE__, 7), 2);
        define('PHAR_COPY', sys_get_temp_dir().'/phar-'.bin2hex(random_bytes(10)).'.phar');

        copy($pharPath, \PHAR_COPY);

        $autoload = 'phar://'.\PHAR_COPY.'/vendor/humbug/php-scoper/src/functions.php';

        register_shutdown_function(static function (): void {
            @unlink(\PHAR_COPY);
        });

        require_once $autoload;

        return;
    }

    throw new RuntimeException('Unable to find the PHP-Scoper functions.');
};

$GLOBALS['_BOX_BOOTSTRAP'] = function () use ($findPhpScoperFunctions): void {
    $findPhpScoperFunctions();

    \KevinGH\Box\register_aliases();
};
$GLOBALS['_BOX_BOOTSTRAP']();

// Convert errors to exceptions
set_error_handler(
    function ($code, $message, $file, $line): void {
        if (error_reporting() & $code) {
            throw new ErrorException($message, 0, $code, $file, $line);
        }
    }
);
