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

function get_prefix(): string
{
    $lastRelease = shell_exec('git describe --abbrev=0 --tags HEAD');

    if (!is_string($lastRelease) || '' === $lastRelease) {
        throw new RuntimeException('Invalid tag name found.');
    }

    return 'HumbugBox'.str_replace('.', '', $lastRelease);
}

return [
    'prefix' => get_prefix(),

    'expose-global-classes' => false,
    'expose-global-constants' => false,
    'expose-global-functions' => false,

    'patchers' => [
        // TODO: report back the missing sapi_windows_vt100_support to JetBrains stubs
        static function (string $filePath, string $prefix, string $contents): string {
            $files = [
                'vendor/sebastian/environment/src/Console.php',
                'src/IO.php',
            ];

            if (false === in_array($filePath, $files, true)) {
                return $contents;
            }

            $contents = preg_replace(
                '/function_exists\(\''.$prefix.'\\\\(\\\\)?sapi_windows_vt100_support\'\)/',
                "function_exists('sapi_windows_vt100_support')",
                $contents
            );

            return $contents;
        },
    ],
];
