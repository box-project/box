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

namespace KevinGH\Box\RequirementChecker;

use function array_filter;
use function array_keys;
use function array_values;
use function str_starts_with;

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
     * @return list<PackageInfo>
     */
    public function getPackages(): array
    {
        return array_values(
            array_map(
                static fn (string $packageName) => new PackageInfo(['name' => $packageName]),
                array_filter(
                    array_keys($this->composerJsonDecodedContents['require'] ?? []),
                    static fn (string $packageName) => 'php' !== $packageName && !str_starts_with($packageName, 'ext-'),
                ),
            ),
        );
    }
}
