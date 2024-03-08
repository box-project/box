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

use KevinGH\Box\Composer\Artifact\ComposerJson;
use KevinGH\Box\Composer\Artifact\ComposerLock;
use Symfony\Component\Filesystem\Path;
use function array_filter;
use function array_map;
use function realpath;
use const DIRECTORY_SEPARATOR;

/**
 * @private
 */
final class ComposerConfiguration
{
    private const DEFAULT_VENDOR_DIR = 'vendor';

    /**
     * Attempts to locate the `composer.json` and `composer.lock` files in the provided base-path in order to collect
     * all the dev packages.
     *
     * @return string[] Dev package paths
     */
    public static function retrieveDevPackages(
        string $basePath,
        ?ComposerJson $composerJson,
        ?ComposerLock $composerLock,
        bool $excludeDevPackages,
    ): array {
        if (null === $composerJson
            || null === $composerLock
            || false === $excludeDevPackages
        ) {
            return [];
        }

        return self::getDevPackagePaths(
            $basePath,
            $composerJson,
            $composerLock,
        );
    }

    /**
     * @return string[] Dev packages paths
     */
    private static function getDevPackagePaths(
        string $basePath,
        ComposerJson $composerJson,
        ComposerLock $composerLock,
    ): array {
        $vendorDir = Path::makeAbsolute(
            self::retrieveVendorDir($composerJson),
            $basePath,
        );
        $packageNames = $composerLock->getDevPackageNames();

        $mapPackageNameToRealPath = static function (string $packageName) use ($vendorDir): ?string {
            $realPath = realpath($vendorDir.DIRECTORY_SEPARATOR.$packageName);

            return false !== $realPath ? $realPath : null;
        };

        return array_filter(
            array_map(
                $mapPackageNameToRealPath,
                $packageNames,
            ),
        );
    }

    public static function retrieveVendorDir(?ComposerJson $composerJson): string
    {
        return $composerJson?->getVendorDir() ?? self::DEFAULT_VENDOR_DIR;
    }
}
