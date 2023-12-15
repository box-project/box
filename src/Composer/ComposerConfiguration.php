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

namespace KevinGH\Box\Composer;

use KevinGH\Box\Composer\Artifact\DecodedComposerJson;
use Symfony\Component\Filesystem\Path;
use function array_column;
use function array_filter;
use function array_key_exists;
use function array_map;
use function realpath;
use const DIRECTORY_SEPARATOR;

/**
 * @private
 */
final class ComposerConfiguration
{
    // TODO: move more things of this class into the decoded
    private const DEFAULT_VENDOR_DIR = 'vendor';

    /**
     * Attempts to locate the `composer.json` and `composer.lock` files in the provided base-path in order to collect
     * all the dev packages.
     *
     * @return string[] Dev package paths
     */
    public static function retrieveDevPackages(
        string $basePath,
        ?array $composerJsonDecodedContents,
        ?array $composerLockDecodedContents,
        bool $excludeDevPackages,
    ): array {
        if (null === $composerJsonDecodedContents
            || null === $composerLockDecodedContents
            || false === $excludeDevPackages
        ) {
            return [];
        }

        return self::getDevPackagePaths(
            $basePath,
            $composerJsonDecodedContents,
            $composerLockDecodedContents,
        );
    }

    /**
     * @return string[] Dev packages paths
     */
    private static function getDevPackagePaths(
        string $basePath,
        array $composerJsonDecodedContents,
        array $composerLockDecodedContents,
    ): array {
        $vendorDir = Path::makeAbsolute(
            self::retrieveVendorDir($composerJsonDecodedContents),
            $basePath,
        );

        $packageNames = self::retrieveDevPackageNames($composerLockDecodedContents);

        return array_filter(
            array_map(
                static function (string $packageName) use ($vendorDir): ?string {
                    $realPath = realpath($vendorDir.DIRECTORY_SEPARATOR.$packageName);

                    return false !== $realPath ? $realPath : null;
                },
                $packageNames,
            ),
        );
    }

    public static function retrieveVendorDir(?DecodedComposerJson $composerJson): string
    {
        return $composerJson?->getVendorDir() ?? self::DEFAULT_VENDOR_DIR;
    }

    /**
     * @return string[] Names of the dev packages
     */
    private static function retrieveDevPackageNames(array $composerLockDecodedContents): array
    {
        if (false === array_key_exists('packages-dev', $composerLockDecodedContents)) {
            return [];
        }

        return array_column($composerLockDecodedContents['packages-dev'], 'name');
    }
}
