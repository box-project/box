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

use function preg_match;
use function str_replace;

final class DisplayNormalizer
{
    public static function removeTrailingSpaces(string $display): string
    {
        $lines = explode("\n", $display);

        $lines = array_map(
            'rtrim',
            $lines
        );

        return implode("\n", $lines);
    }

    public static function removeMiddleStringLineReturns(string $string): string
    {
        if (1 === preg_match('/^[\S\n]*(?<innerString>[\s\S]+?)[\S\n]*$/', $string, $matches)) {
            $innerString = $matches['innerString'];

            $normalizedInnerString = preg_replace('/[\n\t\s]+/', ' ', $innerString);

            return str_replace($innerString, $normalizedInnerString, $string);
        }

        return $string;
    }

    private function __construct()
    {
    }
}
