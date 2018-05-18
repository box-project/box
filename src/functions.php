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

use Assert\Assertion;
use Phar;
use function array_key_exists;
use function constant;
use function define;
use function defined;

/**
 * TODO: this function should be pushed down to the PHAR extension.
 *
 * @private
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

/**
 * @private
 */
function get_phar_compression_algorithm_extension(int $algorithm): ?string
{
    static $extensions = [
        Phar::GZ => 'zlib',
        Phar::BZ2 => 'bz2',
        Phar::NONE => null,
    ];

    Assertion::true(array_key_exists($algorithm, $extensions));

    return $extensions[$algorithm];
}

/**
 * @private
 */
function formatted_filesize(string $path): string
{
    Assertion::file($path);

    $size = filesize($path);
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $power = $size > 0 ? (int) floor(log($size, 1024)) : 0;

    return sprintf(
        '%s%s',
        number_format(
            $size / (1024 ** $power),
            2,
            '.',
            ','
        ),
        $units[$power]
    );
}

/**
 * @private Converts a memory string, e.g. '2000M' to bytes
 */
function memory_to_bytes(string $value): int
{
    $unit = strtolower($value[strlen($value) - 1]);

    $value = (int) $value;
    switch ($unit) {
        case 'g':
            $value *= 1024;
        // no break (cumulative multiplier)
        case 'm':
            $value *= 1024;
        // no break (cumulative multiplier)
        case 'k':
            $value *= 1024;
    }

    return $value;
}

/**
 * @private
 */
function register_aliases(): void
{
    // Exposes the finder used by PHP-Scoper PHAR to allow its usage in the configuration file.
    if (false === class_exists(\Isolated\Symfony\Component\Finder\Finder::class)) {
        class_alias(\Symfony\Component\Finder\Finder::class, \Isolated\Symfony\Component\Finder\Finder::class);
    }

    // Register compactors aliases
    if (false === class_exists(\Herrera\Box\Compactor\Json::class, false)) {
        class_alias(\KevinGH\Box\Compactor\Json::class, \Herrera\Box\Compactor\Json::class);
    }

    if (false === class_exists(\Herrera\Box\Compactor\Php::class, false)) {
        class_alias(\KevinGH\Box\Compactor\Php::class, \Herrera\Box\Compactor\Php::class);
    }
}

/**
 * @private
 */
function disable_parallel_processing(): void
{
    define(NO_PARALLEL_PROCESSING, true);
}

/**
 * @private
 */
function is_parallel_processing_enabled(): bool
{
    return false === defined(NO_PARALLEL_PROCESSING) || false === constant(NO_PARALLEL_PROCESSING);
}
