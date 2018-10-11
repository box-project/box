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

use Isolated\Symfony\Component\Finder\Finder;

$classLoaderContents = file_get_contents(__DIR__.'/vendor/composer/composer/src/Composer/Autoload/ClassLoader.php');

return [
    'patchers' => [
        function (string $filePath, string $prefix, string $contents): string {
            $finderClass = sprintf('\%s\%s', $prefix, Finder::class);

            return str_replace($finderClass, '\\'.Finder::class, $contents);
        },
        function (string $filePath, string $prefix, string $contents): string {
            $file = 'vendor/beberlei/assert/lib/Assert/Assertion.php';

            if ($filePath !== $file) {
                return $contents;
            }

            return preg_replace(
                "/exceptionClass = 'Assert\\\\\\\\InvalidArgumentException'/",
                sprintf(
                    'exceptionClass = \'%s\\\\Assert\\\\InvalidArgumentException\'',
                    $prefix
                ),
                $contents
            );
        },
        function (string $filePath, string $prefix, string $contents): string {
            $files = [
                'src/functions.php',
                'src/Configuration.php',
            ];

            if (false === in_array($filePath, $files, true)) {
                return $contents;
            }

            $contents = preg_replace(
                sprintf(
                    '/\\\\'.$prefix.'\\\\Herrera\\\\Box\\\\Compactor/',
                    $prefix
                ),
                '\\Herrera\\\Box\\Compactor',
                $contents
            );

            return preg_replace(
                sprintf(
                    '/\\\\'.$prefix.'\\\\KevinGH\\\\Box\\\\Compactor\\\\/',
                    $prefix
                ),
                '\\KevinGH\\\Box\\Compactor\\',
                $contents
            );
        },
        function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/composer/composer/src/Composer/Autoload/AutoloadGenerator.php' !== $filePath) {
                return $contents;
            }

            return preg_replace(
                sprintf(
                    '/\$loader = new \\\\%s\\\\Composer\\\\Autoload\\\\ClassLoader\(\)/',
                    $prefix
                ),
                '$loader = new \Composer\Autoload\ClassLoader();',
                $contents
            );
        },
        function (string $filePath, string $prefix, string $contents) use ($classLoaderContents): string {
            return 'vendor/composer/composer/src/Composer/Autoload/ClassLoader.php' === $filePath ? $classLoaderContents : $contents;
        },
        function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/paragonie/sodium_compat/autoload.php' !== $filePath) {
                return $contents;
            }

            return preg_replace(
                '/\'sodiumCompatAutoloader\'/',
                sprintf(
                    '\'%s\\%s\'',
                    $prefix,
                    'sodiumCompatAutoloader'
                ),
                preg_replace(
                    '/\$namespace = \'ParagonIE_Sodium_\';/',
                    sprintf(
                        '$namespace = \'%s\\ParagonIE_Sodium_\';',
                        $prefix
                    ),
                    $contents
                )
            );
        },
        function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/paragonie/sodium_compat/lib/php72compat.php' !== $filePath) {
                return $contents;
            }

            return preg_replace(
                '/\\\\define\\("SODIUM_{\\$constant}", \\\\constant\\("ParagonIE_Sodium_Compat::{\\$constant}"\\)\\);/',
                sprintf(
                    '\define("SODIUM_{$constant}", \constant("%s\\ParagonIE_Sodium_Compat::{$constant}"));',
                    $prefix
                ),
                $contents
            );
        },
        // `stream_isatty()` is a PHP 7.2 function hence not defined in PHP 7.1. Unlike its function call, the string
        // in `function_exists()` is prefixed because the name can be resolved to a FQCN and appears as a user-land
        // function.
        function (string $filePath, string $prefix, string $contents): string {
            $files = [
                'vendor/symfony/console/Output/StreamOutput.php',
                'vendor/symfony/var-dumper/Dumper/CliDumper.php',
                'vendor/composer/xdebug-handler/src/Process.php',
            ];

            if (false === in_array($filePath, $files, true)) {
                return $contents;
            }

            return preg_replace(
                '/function_exists\(\''.$prefix.'\\\\\\\\stream_isatty\'\)/',
                "function_exists('stream_isatty')",
                $contents
            );
        },
    ],
    'whitelist' => [
        \Composer\Autoload\ClassLoader::class,

        \Herrera\Box\Compactor\Json::class,
        \KevinGH\Box\Compactor\Json::class,
        \Herrera\Box\Compactor\Php::class,
        \KevinGH\Box\Compactor\Php::class,
        \KevinGH\Box\Compactor\PhpScoper::class,
    ],
];
