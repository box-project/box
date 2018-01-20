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

namespace KevinGH\Box\Exception;

use Exception;

/**
 * The OpenSSL exceptions API is a bit lacking/inconvenient to use so this factory mostly serves as an helper to get
 * around it.
 *
 * @private
 */
final class OpenSslExceptionFactory
{
    public static function createForLastError(): Exception
    {
        return new Exception(openssl_error_string());
    }

    /**
     * Clears the error buffer, preventing unassociated error messages from being used by the `lastError()` method. This
     * is required for `lastError()` to function properly. If the clearing loop continues beyond a certain number, a
     * warning will be triggered before the loop is broken.
     *
     * @param int $count the maximum number of rounds
     */
    public static function reset(int $count = 100): void
    {
        $counter = 0;

        while (openssl_error_string()) {
            if ($count < ++$counter) {
                trigger_error(
                    "The OpenSSL error clearing loop has exceeded $count rounds.",
                    E_USER_WARNING
                );

                break;
            }
        }
    }
}
