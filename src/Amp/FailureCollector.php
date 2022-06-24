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

namespace KevinGH\Box\Amp;

use Amp\MultiReasonException;
use function array_map;
use function array_unique;
use KevinGH\Box\NotInstantiable;
use Throwable;

final class FailureCollector
{
    use NotInstantiable;

    /**
     * @return list<string>
     */
    public static function collectReasons(MultiReasonException $exception): array
    {
        return array_unique(
            array_map(
                static fn (Throwable $throwable) => $throwable->getMessage(),
                $exception->getReasons(),
            ),
        );
    }
}
