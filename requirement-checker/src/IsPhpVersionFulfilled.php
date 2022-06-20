<?php

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\RequirementChecker;

use Composer\Semver\Semver;
use function sprintf;

/**
 * @private
 */
final class IsPhpVersionFulfilled implements IsFulfilled
{
    private $requiredPhpVersion;

    public function __construct(string $requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }

    public function __invoke(): bool
    {
        return Semver::satisfies(
            sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION),
            $this->requiredPhpVersion
        );
    }
}
