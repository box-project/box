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

namespace KevinGH\Box\Phar\Throwable;

use Throwable;
use UnexpectedValueException;
use function Safe\preg_match;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function ucfirst;

final class InvalidPhar extends PharError
{
    public static function fileNotLocal(
        string $file,
        ?string $originalFile = null,
    ): self {
        // Covers:
        // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1328
        return new self(
            sprintf(
                'Could not create a Phar or PharData instance for the file path "%s"%s. PHAR objects can only be created from local files.',
                $file,
                null === $originalFile
                    ? ''
                    : sprintf(
                        ' (of the original file "%s")',
                        $originalFile,
                    ),
            ),
        );
    }

    public static function fileNotFound(
        string $file,
        ?string $originalFile = null,
    ): self {
        return new self(
            sprintf(
                'Could not find the file "%s"%s.',
                $file,
                null === $originalFile
                    ? ''
                    : sprintf(
                        ' (of the original file "%s")',
                        $originalFile,
                    ),
            ),
        );
    }

    public static function fileNotReadable(string $file): self
    {
        return new self(
            sprintf(
                'Could not read the file "%s".',
                $file,
            ),
        );
    }

    public static function forPhar(
        string $file,
        ?string $originalFile,
        ?Throwable $previous,
    ): self {
        return new self(
            self::mapThrowableToErrorMessage($file, $originalFile, $previous, false),
            previous: $previous,
        );
    }

    public static function forPharData(
        string $file,
        ?string $originalFile,
        ?Throwable $previous,
    ): self {
        return new self(
            self::mapThrowableToErrorMessage($file, $originalFile, $previous, true),
            previous: $previous,
        );
    }

    public static function forPharAndPharData(
        string $file,
        ?string $originalFile,
        ?Throwable $previous,
    ): self {
        return new self(
            self::mapThrowableToErrorMessage($file, $originalFile, $previous, null),
            previous: $previous,
        );
    }

    private static function mapThrowableToErrorMessage(
        string $file,
        ?string $originalFile,
        ?Throwable $throwable,
        ?bool $isPharData,
    ): string {
        if (null === $isPharData) {
            $pharObject = 'Phar or PharData';
        } else {
            $pharObject = $isPharData ? 'PharData' : 'Phar';
        }

        $errorMessageStart = sprintf(
            'Could not create a %s instance for the file "%s"%s',
            $pharObject,
            $file,
            null === $originalFile
                ? ''
                : sprintf(
                    ' (of the original file "%s")',
                    $originalFile,
                ),
        );
        $message = $throwable?->getMessage() ?? '';

        if ($throwable instanceof UnexpectedValueException) {
            // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1330
            if (str_ends_with($message, 'file extension (or combination) not recognised or the directory does not exist')) {
                return sprintf(
                    $errorMessageStart.'. The file must have the extension "%s".',
                    $isPharData ? '.zip", ".tar", ".tar.bz2" or ".tar.gz' : '.phar',
                );
            }

            // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1791
            // and a few other similar errors.
            if (str_starts_with($message, 'internal corruption of phar ')) {
                preg_match('/^internal corruption of phar \".+\" \((?<reason>.+)\)$/', $message, $matches);

                return sprintf(
                    $errorMessageStart.'. The archive is corrupted: %s.',
                    ucfirst($matches['reason']),
                );
            }

            // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L874
            // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L892
            // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L903
            if (str_contains($message, ' openssl signature ')) {
                return $errorMessageStart.'. The OpenSSL signature could not be read or verified.';
            }

            // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1002
            // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1012
            // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1024
            // And analogue ones for the other signatures
            if (str_contains($message, ' has a broken signature')
                || str_contains($message, ' signature could not be verified')
                || str_contains($message, ' has a broken or unsupported signature')
            ) {
                return $errorMessageStart.'. The archive signature is broken.';
            }
        }

        return sprintf(
            $errorMessageStart.': %s',
            $message,
        );
    }
}
