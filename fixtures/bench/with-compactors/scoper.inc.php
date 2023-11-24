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

return [
    'exclude-namespaces' => [
        'Symfony\Polyfill',
    ],
    'exclude-classes' => [
        IsolatedFinder::class,
    ],
    'exclude-constants' => [
        // Symfony global constants
        '/^SYMFONY\_[\p{L}_]+$/',
    ],
    'expose-classes' => [
        \Composer\Autoload\ClassLoader::class,

        \BenchTest\Compactor\Compactor::class,
        \BenchTest\Compactor\Json::class,
        \BenchTest\Compactor\Php::class,
        \BenchTest\Compactor\PhpScoper::class,
    ],
];
