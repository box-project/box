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

namespace KevinGH\Box;

use function str_replace;

final class VarDumperNormalizer
{
    public static function normalize(
        string $tmp,
        string $exportedOutput,
    ): string {
        $normalizedOutput = str_replace(
            $tmp,
            '/path/to',
            $exportedOutput,
        );

        // For some reasons sometimes you have "-metadata: null" and other
        // times -metadata: & null.
        // It is not clear what causes the difference: either a OS or PHP
        // version difference.
        return str_replace(
            ': & ',
            ': ',
            $normalizedOutput,
        );
    }

    private function __construct()
    {
    }
}
