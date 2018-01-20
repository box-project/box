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
            $file = 'vendor/justinrainbow/json-schema/src/JsonSchema/Constraints/Factory.php';

            if ($filePath !== $file) {
                return $contents;
            }

            return preg_replace(
                "/'JsonSchema\\\\/",
                "'".$prefix.'\\\\\\JsonSchema\\\\',
                $contents
            );
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
