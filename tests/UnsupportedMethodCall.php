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

use DomainException;
use function sprintf;

final class UnsupportedMethodCall extends DomainException
{
    public static function forMethod(string $className, string $functionName): self
    {
        return new self(
            sprintf(
                'Did not expect "%s::%s()" to be called.',
                $className,
                $functionName,
            ),
        );
    }
}
