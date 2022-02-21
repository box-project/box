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

// TODO: check if the phpStorm stubs should not be included?

$polyfillsBootstraps = array_map(
    static fn (SplFileInfo $fileInfo) => $fileInfo->getPathname(),
    iterator_to_array(
        Finder::create()
            ->files()
            ->in(__DIR__ . '/vendor/symfony/polyfill-*')
            ->name('bootstrap.php'),
        false,
    ),
);

$polyfillsStubs = array_map(
    static fn (SplFileInfo $fileInfo) => $fileInfo->getPathname(),
    iterator_to_array(
        Finder::create()
            ->files()
            ->in(__DIR__ . '/vendor/symfony/polyfill-*/Resources/stubs')
            ->name('*.php'),
        false,
    ),
);

return [
    'exclude-files' => [
        ...$polyfillsBootstraps,
        ...$polyfillsStubs,
    ],
    'patchers' => [
        static function (string $filePath, string $prefix, string $contents): string {
            $finderClass = sprintf('\%s\%s', $prefix, Finder::class);

            return str_replace($finderClass, '\\'.Finder::class, $contents);
        },
        static function (string $filePath, string $prefix, string $contents): string {
            return preg_replace(
                sprintf(
                    '%s\\\\KevinGH\\\\Box\\\\Compactor\\\\',
                    $prefix,
                ),
                'KevinGH\\Box\\Compactor\\',
                $contents,
            );
        },
    ],
    'whitelist' => [
        \Composer\Autoload\ClassLoader::class,

        \KevinGH\Box\Compactor\Compactor::class,
        \KevinGH\Box\Compactor\Json::class,
        \KevinGH\Box\Compactor\Php::class,
        \KevinGH\Box\Compactor\PhpScoper::class,

        // see: https://github.com/humbug/php-scoper/issues/440
        'Symfony\\Polyfill\\*',
    ],
];
