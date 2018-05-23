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

use function KevinGH\Box\FileSystem\make_path_absolute;

/**
 * @private
 */
final class ComposerConfiguration
{
    /**
     * Attempts to locate the `composer.json` and `composer.lock` files in the provided base-path in order to collect
     * all the dev packages.
     *
     * @return string[] Dev package paths
     */
    public static function retrieveDevPackages(string $basePath, ?array $composerJsonDecodedContents, ?array $composerLockDecodedContents): array
    {
        if (null === $composerJsonDecodedContents || null === $composerLockDecodedContents) {
            return [];
        }

        return self::getDevPackagePaths(
            $basePath,
            $composerJsonDecodedContents,
            $composerLockDecodedContents
        );
    }

    /**
     * @return string[] Dev packages paths
     */
    private static function getDevPackagePaths(
        string $basePath,
        array $composerJsonDecodedContents,
        array $composerLockDecodedContents
    ): array {
        $vendorDir = make_path_absolute(
            self::retrieveVendorDir($composerJsonDecodedContents),
            $basePath
        );

        $packageNames = self::retrieveDevPackageNames($composerLockDecodedContents);

        return array_filter(
            array_map(
                function (string $packageName) use ($vendorDir): ?string {
                    $realPath = realpath($vendorDir.DIRECTORY_SEPARATOR.$packageName);

                    return false !== $realPath ? $realPath : null;
                },
                $packageNames
            )
        );
    }

    public static function retrieveVendorDir(array $composerJsonDecodedContents): string
    {
        if (false === array_key_exists('config', $composerJsonDecodedContents)) {
            return 'vendor';
        }

        if (false === array_key_exists('vendor-dir', $composerJsonDecodedContents['config'])) {
            return 'vendor';
        }

        return $composerJsonDecodedContents['config']['vendor-dir'];
    }

    /**
     * @return string[] Names of the dev packages
     */
    private static function retrieveDevPackageNames(array $composerLockDecodedContents): array
    {
        if (false === array_key_exists('packages-dev', $composerLockDecodedContents)) {
            return [];
        }

        return array_map(
            function (array $package): string {
                return $package['name'];
            },
            $composerLockDecodedContents['packages-dev']
        );
    }
}
