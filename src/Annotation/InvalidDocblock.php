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

namespace KevinGH\Box\Annotation;

use Hoa\Compiler\Exception\UnrecognizedToken;
use function sprintf;
use UnexpectedValueException;

/**
 * @private
 */
final class InvalidDocblock extends UnexpectedValueException
{
    public static function createFromHoaUnrecognizedToken(string $docblock, UnrecognizedToken $exception): self
    {
        return new self(
            sprintf(
                'Could not parse the following docblock: "%s". Cause: "%s"',
                $docblock,
                $exception->getMessage()
            ),
            0,
            $exception
        );
    }
}
