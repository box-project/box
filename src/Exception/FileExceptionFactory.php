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

use Assert\Assertion;
use Exception;

/**
 * @private
 * TODO: it should be possible to get rid of those classes with the error handler registered in Application
 * TODO: move under a throwable namespace?
 */
final class FileExceptionFactory
{
    public static function createForLastError(): Exception
    {
        $error = error_get_last();

        Assertion::notSame($error, []);

        return new Exception($error['message'], $error['code'], $error['previous']);
    }
}
