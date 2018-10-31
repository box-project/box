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

return [
    'whitelist-global-classes' => false,
    'whitelist-global-functions' => false,
    'whitelist' => [
        \Composer\Semver\Semver::class,
    ],

    'patchers' => [
        // `stream_isatty()` is a PHP 7.2 function hence not defined in PHP 7.1. Unlike its function call, the string
        // in `function_exists()` is prefixed because the name can be resolved to a FQCN and appears as a user-land
        // function.
        //
        // Function whitelisting is not used here since most checks are in the form of:
        //
        // ```
        // if (function_exists('stream_isatty') return @stream_isatty($stream);
        // ```
        //
        // If the function is simply whitelisted, then it will check if the function `Humbug\stream_isatty` exists which
        // it will always even though the underlying function may not exists.
        //
        // The following patcher can be safely removed however once https://github.com/humbug/php-scoper/issues/278 is
        // fixed.
        //
        function (string $filePath, string $prefix, string $contents): string {
            $files = [
                'vendor/symfony/console/Output/StreamOutput.php',
                'vendor/symfony/phpunit-bridge/DeprecationErrorHandler.php',
                'vendor/symfony/polyfill-php72/bootstrap.php',
                'vendor/symfony/polyfill-php72/Php72.php',
                'vendor/symfony/var-dumper/Dumper/CliDumper.php',
                'vendor/composer/xdebug-handler/src/Process.php',
            ];

            if (false === in_array($filePath, $files, true)) {
                return $contents;
            }

            $contents = preg_replace(
                '/function_exists\(\''.$prefix.'\\\\(\\\\)?stream_isatty\'\)/',
                "function_exists('stream_isatty')",
                $contents
            );

            $contents = preg_replace(
                '/function_exists\(\''.$prefix.'\\\\(\\\\)?sapi_windows_vt100_support\'\)/',
                "function_exists('sapi_windows_vt100_support')",
                $contents
            );

            return $contents;
        },
    ],
];
