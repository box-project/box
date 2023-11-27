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

$jetBrainStubs = (require __DIR__.'/vendor/humbug/php-scoper/res/get-scoper-phpstorm-stubs.php')(
    __DIR__.'/vendor/jetbrains/phpstorm-stubs',
);
$jetBrainStubsPatcher = (require __DIR__.'/vendor/humbug/php-scoper/res/create-scoper-phpstorm-stubs-map-patcher.php')(
    stubsMapPath: __DIR__.'/vendor/jetbrains/phpstorm-stubs/PhpStormStubsMap.php',
);

return [
    'exclude-files' => $jetBrainStubs,
    'exclude-classes' => [
        IsolatedFinder::class,
    ],
    'exclude-constants' => [
        // Symfony global constants
        '/^SYMFONY\_[\p{L}_]+$/',
    ],
    'exclude-functions' => [
        // https://github.com/humbug/php-scoper/pull/894
        'uv_poll_init_socket',
        'uv_signal_init',
        'uv_signal_start',
    ],
    'expose-classes' => [
        \Composer\Autoload\ClassLoader::class,

        \KevinGH\Box\Compactor\Compactor::class,
        \KevinGH\Box\Compactor\Json::class,
        \KevinGH\Box\Compactor\Php::class,
        \KevinGH\Box\Compactor\PhpScoper::class,
    ],
    'patchers' => [
        $jetBrainStubsPatcher,
    ]
];
