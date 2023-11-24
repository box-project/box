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

namespace KevinGH\Box\Phar;

use Fidry\Console\IO;
use KevinGH\Box\Phar\Differ\DifferFactory;
use function array_map;

/**
 * @internal
 */
final class PharDiff
{
    private readonly PharInfo $pharInfoA;
    private readonly PharInfo $pharInfoB;
    private readonly DifferFactory $differFactory;

    public function __construct(string $pathA, string $pathB)
    {
        [$pharInfoA, $pharInfoB] = array_map(
            static fn (string $path) => new PharInfo($path),
            [$pathA, $pathB],
        );

        $this->pharInfoA = $pharInfoA;
        $this->pharInfoB = $pharInfoB;

        $this->differFactory = new DifferFactory();
    }

    public function getPharInfoA(): PharInfo
    {
        return $this->pharInfoA;
    }

    public function getPharInfoB(): PharInfo
    {
        return $this->pharInfoB;
    }

    public function equals(): bool
    {
        return $this->pharInfoA->equals($this->pharInfoB);
    }

    public function diff(DiffMode $mode, string $checksumAlgorithm, IO $io): void
    {
        $this->differFactory
            ->create($mode, $checksumAlgorithm)
            ->diff(
                $this->pharInfoA,
                $this->pharInfoB,
                $io,
            );
    }
}
