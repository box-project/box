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
    'patchers' => [
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
    ],
    'whitelist' => [
        \Herrera\Box\Compactor\Javascript::class,
        \KevinGH\Box\Compactor\Javascript::class,
        \Herrera\Box\Compactor\Json::class,
        \KevinGH\Box\Compactor\Json::class,
        \Herrera\Box\Compactor\Php::class,
        \KevinGH\Box\Compactor\Php::class,
        \KevinGH\Box\Compactor\PhpScoper::class,
    ],
];
