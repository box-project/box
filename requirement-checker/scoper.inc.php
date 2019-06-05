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
    if (false !== getenv('TRAVIS') && false === getenv('GITHUB_TOKEN')) {
        // Ignore this PR to avoid too many builds to fail untimely or locally due to API rate limits because the last
        // release version could not be retrieved.
        return 'HumbugBoxTemporaryPrefix';
    }

    $gitHubToken = getenv('GITHUB_TOKEN');

    $headerOption = false === $gitHubToken || '' === $gitHubToken
        ? ''
        : "-H \"Authorization: token $gitHubToken\""
    ;

    $lastReleaseEndpointContents = shell_exec(<<<BASH
curl -s $headerOption https://api.github.com/repos/humbug/box/releases/latest
BASH
    );

    if (null === $lastReleaseEndpointContents) {
        throw new RuntimeException('Could not retrieve the last release endpoint.');
    }

    $contents = json_decode($lastReleaseEndpointContents, false, 512, JSON_PRETTY_PRINT);

    if (JSON_ERROR_NONE !== json_last_error()) {
        // TODO: switch to safe json parsing in the future
        throw new RuntimeException(
            sprintf(
                'Could not parse the request contents: "%d: %s"',
                json_last_error(),
                json_last_error_msg()
            )
        );
    }

    if (false === isset($contents->tag_name) || false === is_string($contents->tag_name)) {
        throw new RuntimeException(
            sprintf(
                'No tag name could be found in: %s',
                $lastReleaseEndpointContents
            )
        );
    }

    $lastRelease = trim($contents->tag_name);

    if ('' === $lastRelease) {
        throw new RuntimeException('Invalid tag name found.');
    }

    return 'HumbugBox'.str_replace('.', '', $lastRelease);
}

return [
    'prefix' => get_prefix(),

    'whitelist-global-classes' => false,
    'whitelist-global-constants' => false,
    'whitelist-global-functions' => false,

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
