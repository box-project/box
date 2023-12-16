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

namespace KevinGH\Box\Composer\Artifact;

use KevinGH\Box\Composer\Package\Extensions;
use KevinGH\Box\Composer\Package\PackageInfo;
use KevinGH\Box\Composer\Package\RequiredItem;
use function array_key_exists;
use function array_keys;
use function current;

/**
 * @private
 */
final readonly class ComposerJson
{
    /**
     * @param array $decodedContents Decoded JSON contents of the `composer.json` file
     */
    public function __construct(
        public string $path,
        public array $decodedContents,
    ) {
    }

    public function getRequiredPhpVersion(): ?string
    {
        return $this->decodedContents['require']['php'] ?? null;
    }

    public function hasRequiredPhpVersion(): bool
    {
        return null !== $this->getRequiredPhpVersion();
    }

    /**
     * @return RequiredItem[]
     */
    public function getRequiredItems(): array
    {
        $require = $this->decodedContents['require'] ?? [];

        return array_map(
            static fn (string $packageName) => new RequiredItem([$packageName => $require[$packageName]]),
            array_keys($require),
        );
    }

    public function getConflictingExtensions(): Extensions
    {
        return PackageInfo::parseExtensions(
            $this->decodedContents['conflict'] ?? [],
        );
    }

    public function getVendorDir(): ?string
    {
        return $this->decodedContents['config']['vendor-dir'] ?? null;
    }

    public function getFirstBin(): ?string
    {
        $firstBin = current((array) ($this->decodedContents['bin'] ?? []));

        return false === $firstBin ? null : $firstBin;
    }
}
