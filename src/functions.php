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

use function array_key_exists;
use Assert\Assertion;
use function bin2hex;
use function class_alias;
use function class_exists;
use Closure;
use function constant;
use function define;
use function defined;
use function floor;
use KevinGH\Box\Console\IO\IO;
use KevinGH\Box\Console\Php\PhpSettingsHandler;
use function KevinGH\Box\FileSystem\copy;
use function log;
use function number_format;
use PackageVersions\Versions;
use Phar;
use function random_bytes;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Logger\ConsoleLogger;

/**
 * @private
 */
function get_box_version(): string
{
    $rawVersion = Versions::getVersion('humbug/box');

    [$prettyVersion, $commitHash] = explode('@', $rawVersion);

    return $prettyVersion.'@'.substr($commitHash, 0, 7);
}

/**
 * @private
 *
 * @return <string, int>
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

    Assertion::true(
        array_key_exists($algorithm, $extensions),
        sprintf('Unknown compression algorithm code "%d"', $algorithm)
    );

    return $extensions[$algorithm];
}

/**
 * @private
 *
 * @return <string, int>
 */
function get_phar_signing_algorithms(): array
{
    static $algorithms = [
        'MD5' => Phar::MD5,
        'SHA1' => Phar::SHA1,
        'SHA256' => Phar::SHA256,
        'SHA512' => Phar::SHA512,
        'OPENSSL' => Phar::OPENSSL,
    ];

    return $algorithms;
}

/**
 * @private
 */
function format_size(int $size, int $decimals = 2): string
{
    if (-1 === $size) {
        return '-1';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    $power = $size > 0 ? (int) floor(log($size, 1024)) : 0;

    return sprintf(
        '%s%s',
        number_format(
            $size / (1024 ** $power),
            $decimals,
            '.',
            ','
        ),
        $units[$power]
    );
}

/**
 * @private
 */
function memory_to_bytes(string $value): int
{
    $unit = strtolower($value[strlen($value) - 1]);

    $bytes = (int) $value;
    switch ($unit) {
        case 'g':
            $bytes *= 1024;
        // no break (cumulative multiplier)
        case 'm':
            $bytes *= 1024;
        // no break (cumulative multiplier)
        case 'k':
            $bytes *= 1024;
    }

    return $bytes;
}

/**
 * @private
 */
function format_time(float $secs): string
{
    return str_replace(
        ' ',
        '',
        Helper::formatTime($secs)
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
    if (false === defined(_NO_PARALLEL_PROCESSING)) {
        define(_NO_PARALLEL_PROCESSING, true);
    }
}

/**
 * @private
 */
function is_parallel_processing_enabled(): bool
{
    return false === defined(_NO_PARALLEL_PROCESSING) || false === constant(_NO_PARALLEL_PROCESSING);
}

/**
 * @private
 *
 * @return string Random 12 characters long (plus the prefix) string composed of a-z characters and digits
 */
function unique_id(string $prefix): string
{
    return $prefix.bin2hex(random_bytes(6));
}

/**
 * @private
 */
function create_temporary_phar(string $file): string
{
    $tmpFile = sys_get_temp_dir().'/'.unique_id('').basename($file);

    if ('' === pathinfo($file, PATHINFO_EXTENSION)) {
        $tmpFile .= '.phar';
    }

    copy($file, $tmpFile, true);

    return $tmpFile;
}

/**
 * @private
 */
function check_php_settings(IO $io): void
{
    (new PhpSettingsHandler(
        new ConsoleLogger(
            $io->getOutput()
        )
    ))->check();
}

/**
 * @private
 */
function noop(): Closure
{
    return static function (): void {};
}
