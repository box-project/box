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

namespace KevinGH\Box\Pharaoh;

use Throwable;
use UnexpectedValueException;
use function preg_match;
use function sprintf;
use function str_starts_with;

final class InvalidPhar extends PharError
{
    public static function create(string $file, ?Throwable $previous): self
    {
        return new self(
            self::mapThrowableToErrorMessage($file, $previous),
            previous: $previous,
        );
    }

    private static function mapThrowableToErrorMessage(string $file, ?Throwable $throwable): string
    {
        if ($throwable instanceof UnexpectedValueException) {
            // https://github.com/php/php-src/blob/930db2b2d315b2acc917706cf76bed8b09f94b79/ext/phar/phar.c#L1328
            if (str_starts_with($throwable->getMessage(), 'Cannot create a phar archive from a URL like')) {
                return sprintf(
                    'Cannot create a PHAR object from a URL like "%s". PHAR objects can only be created from local files.',
                    $file,
                );
            }
        }

        return self::couldNotVerifyOpenSSLSignature($throwable)
            ? sprintf(
                'Could not create a Phar or PharData instance for the file "%s": the OpenSSL signature could not be verified.',
                $file,
            )
            : sprintf(
                'Could not create a Phar or PharData instance for the file "%s".',
                $file,
            );
    }

    private static function couldNotVerifyOpenSSLSignature(?Throwable $previous): bool
    {
        return null !== $previous
            && 1 === preg_match(
                '/^phar ".*" openssl signature could not be verified: openssl signature could not be verified/',
                $previous->getMessage(),
            );
    }
}
