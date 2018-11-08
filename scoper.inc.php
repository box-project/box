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

return [
    'patchers' => [
        // TODO: to check if still necessary
        function (string $filePath, string $prefix, string $contents): string {
            $finderClass = sprintf('\%s\%s', $prefix, Finder::class);

            return str_replace($finderClass, '\\'.Finder::class, $contents);
        },
        // Box compactors: not required to work but avoid any confusion for the users
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
        // Paragonie custom autoloader which relies on some regexes
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
        // Paragonie dynamic constants declarations
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
        // Hoa patches
        function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/hoa/stream/Stream.php' !== $filePath) {
                return $contents;
            }

            return preg_replace(
                '/Hoa\\\\Consistency::registerShutdownFunction\(xcallable\(\'(.*)\'\)\)/',
                sprintf(
                    'Hoa\\Consistency::registerShutdownFunction(xcallable(\'%s$1\'))',
                    $prefix.'\\\\\\\\'
                ),
                $contents
            );
        },
        function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/hoa/consistency/Autoloader.php' !== $filePath) {
                return $contents;
            }

            $contents = preg_replace(
                '/(\$entityPrefix = \$entity;)/',
                sprintf(
                    '$entity = substr($entity, %d);$1',
                    strlen($prefix) + 1
                ),
                $contents
            );

            $contents = preg_replace(
                '/return \$this->runAutoloaderStack\((.*)\);/',
                sprintf(
                    'return $this->runAutoloaderStack(\'%s\'.\'%s\'.$1);',
                    $prefix,
                    '\\\\\\'
                ),
                $contents
            );

            return $contents;
        },
    ],
    'files-whitelist' => [
        __DIR__.'/vendor/composer/composer/src/Composer/Autoload/ClassLoader.php',
    ],
    'whitelist' => [
        \Composer\Autoload\ClassLoader::class,

        \Herrera\Box\Compactor\Json::class,
        \KevinGH\Box\Compactor\Json::class,
        \Herrera\Box\Compactor\Php::class,
        \KevinGH\Box\Compactor\Php::class,
        \KevinGH\Box\Compactor\PhpScoper::class,

        // Hoa symbols
        'SUCCEED',
        'FAILED',
        '…',
        'DS',
        'PS',
        'ROOT_SEPARATOR',
        'RS',
        'CRLF',
        'OS_WIN',
        'S_64_BITS',
        'S_32_BITS',
        'PHP_INT_MIN',
        'PHP_FLOAT_MIN',
        'PHP_FLOAT_MAX',
        'PHP_WINDOWS_VERSION_PLATFORM',
        'π',
        'nil',
        '_public',
        '_protected',
        '_private',
        '_static',
        '_abstract',
        '_pure',
        '_final',
        '_dynamic',
        '_concrete',
        '_overridable',
        'WITH_COMPOSER',
        'xcallable',
    ],
    'whitelist-global-constants' => false,
    'whitelist-global-classes' => false,
    'whitelist-global-functions' => false,
];
