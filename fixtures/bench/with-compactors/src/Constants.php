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

namespace BenchTest;

final class Constants
{
    use NotInstantiable;

    public const MEMORY_LIMIT = 'BOX_MEMORY_LIMIT';
    public const ALLOW_XDEBUG = 'BOX_ALLOW_XDEBUG';
    public const BIN = 'BOX_BIN';
}
