<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

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