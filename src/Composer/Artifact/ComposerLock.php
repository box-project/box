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
use function array_column;
use function array_map;

/**
 * @private
 */
final readonly class ComposerLock
{
    /**
     * @param array $decodedContents Decoded JSON contents of the `composer.lock` file
     */
    public function __construct(
        public string $path,
        public array $decodedContents,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->decodedContents;
    }

    public function getRequiredPhpVersion(): ?string
    {
        return $this->decodedContents['platform']['php'] ?? null;
    }

    public function hasRequiredPhpVersion(): bool
    {
        return null !== $this->getRequiredPhpVersion();
    }

    public function getPlatformExtensions(): Extensions
    {
        return PackageInfo::parseExtensions($this->decodedContents['platform'] ?? []);
    }

    /**
     * @return PackageInfo[]
     */
    public function getPackages(): array
    {
        return array_map(
            static fn (array $package) => new PackageInfo($package),
            $this->decodedContents['packages'] ?? [],
        );
    }

    /**
     * @return string[] Names of the dev packages
     */
    public function getDevPackageNames(): array
    {
        return array_column($this->decodedContents['packages-dev'] ?? [], 'name');
    }
}
