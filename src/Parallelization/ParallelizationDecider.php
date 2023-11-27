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

use KevinGH\Box\NotInstantiable;
use KevinGH\Box\PhpScoper\NullScoper;
use KevinGH\Box\PhpScoper\Scoper;

/**
 * @private
 */
final class ParallelizationDecider
{
    use NotInstantiable;

    private const MIN_FILE_COUNT = 5 * ParallelFileProcessor::FILE_CHUNK_SIZE;

    public static function shouldProcessFilesInParallel(
        Scoper $scoper,
        int $filesCount,
    ): bool {
        return !($scoper instanceof NullScoper || $filesCount < self::MIN_FILE_COUNT);
    }
}
