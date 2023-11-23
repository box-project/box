<?php

declare(strict_types=1);

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

    private const MIN_FILE_COUNT = 500;

    public static function shouldProcessFilesInParallel(
        Scoper $scoper,
        int $filesCount,
    ): bool
    {
        return !($scoper instanceof NullScoper || $filesCount < self::MIN_FILE_COUNT);
    }
}