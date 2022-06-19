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

namespace KevinGH\Box\Console;

use function array_map;
use function explode;
use function implode;
use KevinGH\Box\NotInstantiable;

final class DisplayNormalizer
{
    use NotInstantiable;

    public static function removeTrailingSpaces(string $display): string
    {
        $lines = explode("\n", $display);

        $lines = array_map(
            'rtrim',
            $lines,
        );

        return implode("\n", $lines);
    }
}
