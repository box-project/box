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
use function preg_match;
use function sprintf;

final class InvalidPhar extends PharError
{
    public static function create(string $file, ?Throwable $previous): self
    {
        return new self(
            self::couldNotVerifyOpenSSLSignature($previous)
                ? sprintf(
                    'Could not create a Phar or PharData instance for the file "%s": the OpenSSL signature could not be verified.',
                    $file,
                )
                : sprintf(
                    'Could not create a Phar or PharData instance for the file "%s".',
                    $file,
                ),
            previous: $previous,
        );
    }

    private static function couldNotVerifyOpenSSLSignature(?Throwable $previous): bool
    {
        return null !== $previous
            && 1 === preg_match(
                '/^phar ".*" openssl signature could not be verified: openssl public key could not be read$/',
                $previous->getMessage(),
            );
    }
}
