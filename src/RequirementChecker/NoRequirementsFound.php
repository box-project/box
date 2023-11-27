<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

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