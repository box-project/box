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
use function trim;

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

    public function hasAutoload(): bool
    {
        return array_key_exists('autoload', $this->decodedContents);
    }

    /**
     * @return string[]
     */
    public function getAutoloadPaths(): array
    {
        $autoload = $this->decodedContents['autoload'] ?? [];
        $paths = [];

        if (array_key_exists('psr-4', $autoload)) {
            foreach ($autoload['psr-4'] as $path) {
                /** @var string|string[] $path */
                $composerPaths = (array) $path;

                foreach ($composerPaths as $composerPath) {
                    $paths[] = trim($composerPath);
                }
            }
        }

        if (array_key_exists('psr-0', $autoload)) {
            foreach ($autoload['psr-0'] as $path) {
                /** @var string|string[] $path */
                $composerPaths = (array) $path;

                foreach ($composerPaths as $composerPath) {
                    $paths[] = trim($composerPath);
                }
            }
        }

        if (array_key_exists('classmap', $autoload)) {
            foreach ($autoload['classmap'] as $path) {
                // @var string $path
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @return string[]
     */
    public function getAutoloadFiles(): array
    {
        return $this->decodedContents['autoload']['files'] ?? [];
    }
}
