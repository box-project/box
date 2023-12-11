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

namespace KevinGH\Box\Composer\Throwable;

use RuntimeException;
use function sprintf;

final class IncompatibleComposerVersion extends RuntimeException
{
    public static function create(string $version, string $constraints): self
    {
        return new self(
            sprintf(
                'The Composer version "%s" does not satisfy the constraint "%s".',
                $version,
                $constraints,
            )
        );
    }
}
