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

namespace KevinGH\Box\Phar\Differ;

use Fidry\Console\IO;
use KevinGH\Box\Phar\PharInfo;

interface Differ
{
    public const NO_DIFF_MESSAGE = 'No difference could be observed with this mode.';

    public function diff(
        PharInfo $pharInfoA,
        PharInfo $pharInfoB,
        IO $io,
    ): void;
}
