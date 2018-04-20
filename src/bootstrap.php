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

$findAutoloader = function () {
    if (file_exists($autoload = __DIR__.'/../../../autoload.php')) {
        // Is installed via Composer
        return $autoload;
    }

    if (file_exists($autoload = __DIR__.'/../vendor/autoload.php')) {
        // Is installed locally
        return $autoload;
    }

    throw new RuntimeException('Unable to find the Composer or PHP-Scoper autoloader.');
};

// TODO: update PHP-Scoper to get rid of this horrible hack at some point
$findPhpScoperFunctions = function () {
    if (file_exists($autoload = __DIR__.'/../../php-scoper/src/functions.php')) {
        // Is installed via Composer
        return $autoload;
    }

    if (file_exists($autoload = __DIR__.'/../vendor/humbug/php-scoper/src/functions.php')) {
        // Is scoped (in PHAR or dumped directory) or is installed locally
        return $autoload;
    }

    throw new RuntimeException('Unable to find the PHP-Scoper functions.');
};

$bootstrap = function () use ($findAutoloader, $findPhpScoperFunctions): void {
    require_once $findAutoloader();
    require_once $findPhpScoperFunctions();

    \KevinGH\Box\register_aliases();
};
$bootstrap();

$GLOBALS['bootstrap'] = $bootstrap;

// Convert errors to exceptions
set_error_handler(
    function ($code, $message, $file, $line): void {
        if (error_reporting() & $code) {
            throw new ErrorException($message, 0, $code, $file, $line);
        }
    }
);
