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
use Isolated\Symfony\Component\Finder\Finder as IsolatedFinder;

// TODO: check if the phpStorm stubs should not be included?

$polyfillsBootstraps = array_map(
    static fn (SplFileInfo $fileInfo) => $fileInfo->getPathname(),
    iterator_to_array(
        Finder::create()
            ->files()
            ->in(__DIR__ . '/vendor/symfony/polyfill-*')
            ->name('bootstrap*.php'),
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
    'exclude-namespaces' => [
        'Symfony\Polyfill'
    ],
    'exclude-classes' => [
        IsolatedFinder::class,
    ],
    'exclude-constants' => [
        // Symfony global constants
        '/^SYMFONY\_[\p{L}_]+$/',
    ],
    'expose-functions' => [
        'trigger_deprecation',
    ],
    'expose-classes' => [
        \Composer\Autoload\ClassLoader::class,

        \KevinGH\Box\Compactor\Compactor::class,
        \KevinGH\Box\Compactor\Json::class,
        \KevinGH\Box\Compactor\Php::class,
        \KevinGH\Box\Compactor\PhpScoper::class,
    ],
];
