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

namespace KevinGH\Box;

use Phar;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

function canonicalize(string $path): string
{
    $lastChar = substr($path, -1);

    $canonical = Path::canonicalize($path);

    return '/' === $lastChar ? $canonical.$lastChar : $canonical;
}

function is_absolute(string $path): bool
{
    static $fileSystem;

    if (null === $fileSystem) {
        $fileSystem = new Filesystem();
    }

    return $fileSystem->isAbsolutePath($path);
}

/**
 * TODO: this function should be pushed down to the PHAR extension
 */
function get_phar_compression_algorithms(): array
{
    static $algorithms = [
        'GZ' => Phar::GZ,
        'BZ2' => Phar::BZ2,
        'NONE' => Phar::NONE,
    ];

    return $algorithms;
}

function register_aliases(): void
{
    if (false === class_exists(\Herrera\Box\Compactor\Javascript::class, false)) {
        class_alias(\KevinGH\Box\Compactor\Javascript::class, \Herrera\Box\Compactor\Javascript::class);
    }

    if (false === class_exists(\Herrera\Box\Compactor\Json::class, false)) {
        class_alias(\KevinGH\Box\Compactor\Json::class, \Herrera\Box\Compactor\Json::class);
    }

    if (false === class_exists(\Herrera\Box\Compactor\Php::class, false)) {
        class_alias(\KevinGH\Box\Compactor\Php::class, \Herrera\Box\Compactor\Php::class);
    }
}
