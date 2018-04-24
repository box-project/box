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

use function array_filter;
use function bin2hex;
use function copy;
use function defined;
use function dirname;
use ErrorException;
use function get_declared_classes;
use Phar;
use function preg_match;
use function random_bytes;
use function register_shutdown_function;
use RuntimeException;
use function substr;
use function sys_get_temp_dir;
use function unlink;
use function var_dump;

$findAutoloader = function () {
//    if (file_exists($autoload = __DIR__.'/../../../autoload.php')) {
//        // Is installed via Composer
//        return $autoload;
//    }
//
//    if (file_exists($autoload = __DIR__.'/../vendor/autoload.php')) {
//        // Is installed locally
//        return $autoload;
//    }
//
//    if ('phar:' === substr(__FILE__, 0, 5)
//        && file_exists($autoload = 'phar://box.phar/vendor/autoload.php')
//    ) {
//        // Is in the PHAR
//        return $autoload;
//    }
//
//    var_dump(array_filter(get_declared_classes(), function ($class) {
//        return 1 === preg_match('/Composer/', $class);
//    }));die;
//
//    throw new RuntimeException('Unable to find the Composer autoloader.');
};

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
        define('PHAR_COPY', sys_get_temp_dir().'/phar-'.bin2hex(random_bytes(10)). '.phar');

        copy($pharPath, \PHAR_COPY);

        $autoload = 'phar://'.\PHAR_COPY. '/vendor/humbug/php-scoper/src/functions.php';

        register_shutdown_function(static function () {
            @unlink(\PHAR_COPY);
        });

        require_once $autoload;

        return;
    }

    throw new RuntimeException('Unable to find the PHP-Scoper functions.');
};

$bootstrap = function () use ($findAutoloader, $findPhpScoperFunctions): void {
//    require_once $findAutoloader();
    $findPhpScoperFunctions();

    \KevinGH\Box\register_aliases();
};
$bootstrap();

$GLOBALS['_BOX_BOOTSTRAP'] = $bootstrap;

// Convert errors to exceptions
set_error_handler(
    function ($code, $message, $file, $line): void {
        if (error_reporting() & $code) {
            throw new ErrorException($message, 0, $code, $file, $line);
        }
    }
);
