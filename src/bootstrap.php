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

($GLOBALS['_BOX_BOOTSTRAP'] = function (): void {
    \KevinGH\Box\register_aliases();
})();

// Convert errors to exceptions
set_error_handler(
    function ($code, $message, $file, $line): void {
        if (error_reporting() & $code) {
            throw new ErrorException($message, 0, $code, $file, $line);
        }
    }
);
