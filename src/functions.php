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

use Composer\InstalledVersions;
use ErrorException;
use Isolated\Symfony\Component\Finder\Finder as IsolatedFinder;
use Phar;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Finder\Finder as SymfonyFinder;
use Webmozart\Assert\Assert;
use function bin2hex;
use function class_alias;
use function class_exists;
use function floor;
use function is_float;
use function is_int;
use function log;
use function number_format;
use function random_bytes;
use function sprintf;
use function str_replace;

/**
 * @private
 */
function get_box_version(): string
{
    // Load manually the InstalledVersions class.
    // Indeed, this class is registered to the autoloader by Composer itself which
    // results an incorrect classmap entry in the scoped code.
    // This strategy avoids having to exclude completely the file from the scoping.
    foreach ([__DIR__.'/../vendor/composer/InstalledVersions.php', __DIR__.'/../../../composer/InstalledVersions.php'] as $file) {
        if (file_exists($file)) {
            require_once $file;
            break;
        }
    }

    $prettyVersion = InstalledVersions::getPrettyVersion('humbug/box');
    $commitHash = InstalledVersions::getReference('humbug/box');

    if (null === $commitHash) {
        return $prettyVersion;
    }

    return $prettyVersion.'@'.mb_substr($commitHash, 0, 7);
}

/**
 * @deprecated since 4.3.0. Use \KevinGH\Box\Phar\CompressionAlgorithm instead.
 * @private
 *
 * @return array<string,int>
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
 * @deprecated Since 4.5.0. Use \KevinGH\Box\Phar\SigningAlgorithm instead.
 *
 * @private
 *
 * @return array<string,int>
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
function format_size(float|int $size, int $decimals = 2): string
{
    Assert::true(is_int($size) || is_float($size));

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
        ),
        $units[$power],
    );
}

/**
 * @private
 */
function memory_to_bytes(string $value): float|int
{
    $unit = mb_strtolower($value[mb_strlen($value) - 1]);

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
        Helper::formatTime($secs),
    );
}

/**
 * @private
 */
function register_aliases(): void
{
    // Exposes the finder used by PHP-Scoper PHAR to allow its usage in the configuration file.
    if (false === class_exists(IsolatedFinder::class)) {
        class_alias(SymfonyFinder::class, IsolatedFinder::class);
    }
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
 * Converts errors to exceptions.
 *
 * @private
 */
function register_error_handler(): void
{
    set_error_handler(
        static function (int $code, string $message, string $file = '', int $line = -1): void {
            if (error_reporting() & $code) {
                throw new ErrorException($message, 0, $code, $file, $line);
            }
        },
    );
}
