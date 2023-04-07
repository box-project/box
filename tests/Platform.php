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

use const PHP_OS_FAMILY;

final class Platform
{
    use NotInstantiable;

    public static function isOSX(): bool
    {
        return 'Darwin' === PHP_OS_FAMILY;
    }
}
