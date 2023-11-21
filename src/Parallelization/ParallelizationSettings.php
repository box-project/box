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

namespace KevinGH\Box\Parallelization;

use KevinGH\Box\Constants;
use KevinGH\Box\NotInstantiable;
use function constant;
use function define;
use function defined;

/**
 * @private
 */
final class ParallelizationSettings
{
    use NotInstantiable;

    public static function disableParallelProcessing(): void
    {
        if (false === defined(Constants::NO_PARALLEL_PROCESSING)) {
            define(Constants::NO_PARALLEL_PROCESSING, true);
        }
    }

    public static function isParallelProcessingEnabled(): bool
    {
        return false === defined(Constants::NO_PARALLEL_PROCESSING)
            || false === constant(Constants::NO_PARALLEL_PROCESSING);
    }
}
