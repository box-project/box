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

use Isolated\Symfony\Component\Finder\Finder as IsolatedFinder;

$polyfillFiles = require __DIR__.'/res/scoper-polyfills.php';
$jetBrainStubFiles = require __DIR__.'/res/scoper-phpstorm-stubs.php';
$jetBrainStubPatcher = require __DIR__.'/res/scoper-phpstorm-stubs-map-patcher.php';

return [
    'exclude-files' => [
        ...$polyfillFiles,
        ...$jetBrainStubFiles,
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
    'patchers' => [
        $jetBrainStubPatcher,
    ]
];
