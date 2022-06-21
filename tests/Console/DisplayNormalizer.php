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

final class DisplayNormalizer
{
    public static function removeTrailingSpaces(string $display): string
    {
        $lines = explode("\n", $display);

        $lines = array_map(
            'rtrim',
            $lines,
        );

        return implode("\n", $lines);
    }

    /**
     * @return callable(string):string
     */
    public static function createLoadingFilePathOutputNormalizer(): callable
    {
        return static fn ($output) => preg_replace(
            '/\s\/\/ Loading the configuration file([\s\S]*)box\.json[comment\<\>\n\s\/]*"\./',
            ' // Loading the configuration file "box.json".',
            $output,
        );
    }

    /**
     * @return callable(string):string
     */
    public static function createVarDumperObjectReferenceNormalizer(): callable
    {
        return static fn ($output) => preg_replace(
            '/ \{#\d{3,}/',
            ' {#140',
            $output,
        );
    }

    /**
     * @return callable(string):string
     */
    public static function createReplaceBoxVersionNormalizer(): callable
    {
        return static fn (string $output): string => preg_replace(
            '/Box version .+@[a-z\d]{7}/',
            'Box version 3.x-dev@151e40a',
            $output,
        );
    }
}
