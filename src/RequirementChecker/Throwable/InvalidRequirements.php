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

namespace KevinGH\Box\RequirementChecker\Throwable;

use RuntimeException;
use function get_debug_type;
use function sprintf;

/**
 * @private
 */
final class InvalidRequirements extends RuntimeException
{
    public static function forRequirements(string $file, mixed $value): self
    {
        return new self(
            sprintf(
                'Could not interpret Box\'s RequirementChecker shipped in "%s". Expected an array got "%s".',
                $file,
                get_debug_type($value),
            ),
        );
    }
}
