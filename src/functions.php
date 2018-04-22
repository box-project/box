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
use Symfony\Component\Console\Output\OutputInterface;
use function constant;
use function define;
use function defined;

/**
 * @internal
 * @private
 */
const NO_PARALLEL_PROCESSING = 'KevinGH\Box\BOX_NO_PARALLEL_PROCESSING';

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
function formatted_filesize(string $path)
{
    Assertion::file($path);

    $size = filesize($path);
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $power = $size > 0 ? floor(log($size, 1024)) : 0;

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
 * @private
 */
function register_aliases(): void
{
    // Exposes the finder used by PHP-Scoper PHAR to allow its usage in the configuration file.
    if (false === class_exists(\Isolated\Symfony\Component\Finder\Finder::class)) {
        class_alias(\Symfony\Component\Finder\Finder::class, \Isolated\Symfony\Component\Finder\Finder::class);
    }

    // Register compactors aliases
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
