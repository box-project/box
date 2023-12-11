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
use function sprintf;

/**
 * @private
 */
final class NoRequirementsFound extends RuntimeException
{
    public static function forFile(string $file): self
    {
        return new self(
            sprintf(
                'Could not find Box\'s RequirementChecker in "%s".',
                $file,
            ),
        );
    }
}
