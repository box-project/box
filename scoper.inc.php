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
        // Hoa patches
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/hoa/stream/Stream.php' !== $filePath) {
                return $contents;
            }

            return preg_replace(
                '/Hoa\\\\Consistency::registerShutdownFunction\(xcallable\(\'(.*)\'\)\)/',
                sprintf(
                    'Hoa\\Consistency::registerShutdownFunction(%sxcallable(\'%s$1\'))',
                    '\\\\'.$prefix.'\\\\',
                    $prefix.'\\\\\\\\'
                ),
                $contents
            );
        },
        static function (string $filePath, string $prefix, string $contents): string {
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
        // PHP-Parser
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/nikic/php-parser/lib/PhpParser/Lexer.php' !== $filePath) {
                return $contents;
            }

            return preg_replace(
                '/\$tokenMap\[.*T_ENUM\] =/',
                '$tokenMap[\T_ENUM] =',
                $contents
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
    ],
];
