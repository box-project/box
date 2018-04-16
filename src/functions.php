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
const DEBUG_CONST = 'KevinGH\Box\BOX_DEBUG';

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
function register_compactor_aliases(): void
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

/**
 * @private
 */
function enable_debug(OutputInterface $output): void
{
    define(DEBUG_CONST, true);

    $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
}

/**
 * @private
 */
function is_debug_enabled(): bool
{
    return defined(DEBUG_CONST) && true === constant(DEBUG_CONST);
}
