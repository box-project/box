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

use Closure;

/**
 * @private
 */
final class Noop
{
    use NotInstantiable;

    /**
     * @var Closure():void
     */
    private static Closure $noop;

    /**
     * @return Closure():void
     */
    public static function create(): Closure
    {
        if (!isset(self::$noop)) {
            self::$noop = static function (): void {};
        }

        return self::$noop;
    }
}
