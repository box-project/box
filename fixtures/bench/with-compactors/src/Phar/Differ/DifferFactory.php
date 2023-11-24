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

namespace BenchTest\Phar\Differ;

use BenchTest\Console\Command\Extract;
use BenchTest\Phar\DiffMode;

final class DifferFactory
{
    public function create(
        DiffMode $mode,
        string $checksumAlgorithm,
    ): Differ {
        return match ($mode) {
            DiffMode::FILE_NAME => new FilenameDiffer(),
            DiffMode::GIT => new GitDiffer(),
            DiffMode::GNU => new ProcessCommandBasedDiffer('diff --exclude='.Extract::PHAR_META_PATH),
            DiffMode::CHECKSUM => new ChecksumDiffer($checksumAlgorithm),
        };
    }
}
