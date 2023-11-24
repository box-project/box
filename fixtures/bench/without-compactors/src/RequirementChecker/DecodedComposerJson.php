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

use function array_keys;

/**
 * @private
 */
final class DecodedComposerJson
{
    /**
     * @param array $composerJsonDecodedContents Decoded JSON contents of the `composer.json` file
     */
    public function __construct(private readonly array $composerJsonDecodedContents)
    {
    }

    public function getRequiredPhpVersion(): ?string
    {
        return $this->composerJsonDecodedContents['require']['php'] ?? null;
    }

    public function hasRequiredPhpVersion(): bool
    {
        return null !== $this->getRequiredPhpVersion();
    }

    /**
     * @return list<RequiredItem>
     */
    public function getRequiredItems(): array
    {
        $require = $this->composerJsonDecodedContents['require'] ?? [];

        return array_map(
            static fn (string $packageName) => new RequiredItem([$packageName => $require[$packageName]]),
            array_keys($require),
        );
    }

    /**
     * @return list<string>
     */
    public function getConflictingExtensions(): array
    {
        return PackageInfo::parseExtensions(
            $this->composerJsonDecodedContents['conflict'] ?? [],
        );
    }
}
