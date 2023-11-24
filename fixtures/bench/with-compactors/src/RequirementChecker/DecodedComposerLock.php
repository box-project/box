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

namespace BenchTest\RequirementChecker;

use function array_map;

/**
 * @private
 */
final class DecodedComposerLock
{
    /**
     * @param array $composerLockDecodedContents Decoded JSON contents of the `composer.lock` file
     */
    public function __construct(private readonly array $composerLockDecodedContents)
    {
    }

    public function isEmpty(): bool
    {
        return [] === $this->composerLockDecodedContents;
    }

    public function getRequiredPhpVersion(): ?string
    {
        return $this->composerLockDecodedContents['platform']['php'] ?? null;
    }

    public function hasRequiredPhpVersion(): bool
    {
        return null !== $this->getRequiredPhpVersion();
    }

    /**
     * @return list<string>
     */
    public function getPlatformExtensions(): array
    {
        return PackageInfo::parseExtensions($this->composerLockDecodedContents['platform'] ?? []);
    }

    /**
     * @return list<PackageInfo>
     */
    public function getPackages(): array
    {
        return array_map(
            static fn (array $package) => new PackageInfo($package),
            $this->composerLockDecodedContents['packages'] ?? [],
        );
    }
}
