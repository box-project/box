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

use InvalidArgumentException;
use KevinGH\Box\Json\Json;
use Seld\JsonLint\ParsingException;
use function KevinGH\Box\FileSystem\file_contents;
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
    public static function retrieveDevPackages(string $basePath, ?string $composerJson, ?string $composerLock): array
    {
        if (null === $composerJson || null === $composerLock) {
            return [];
        }

        $composerJsonContents = file_contents($composerJson);
        $composerLockContents = file_contents($composerLock);

        return self::getDevPackagePaths(
            $basePath,
            $composerJson,
            $composerJsonContents,
            $composerLock,
            $composerLockContents
        );
    }

    /**
     * @param string $basePath
     * @param string $composerFile
     * @param string $composerFileContents
     * @param string $composerLockFile
     * @param string $composerLockFileContents
     *
     * @return string[] Dev packages paths
     */
    private static function getDevPackagePaths(
        string $basePath,
        string $composerFile,
        string $composerFileContents,
        string $composerLockFile,
        string $composerLockFileContents
    ): array {
        $vendorDir = make_path_absolute(
            self::getVendorDir($composerFile, $composerFileContents),
            $basePath
        );

        $packageNames = self::getDevPackageNames($composerLockFile, $composerLockFileContents);

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

    private static function getVendorDir(string $composerFile, string $composerFileContents): string
    {
        try {
            $config = (array) (new Json())->decode($composerFileContents, true);
        } catch (ParsingException $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected the file "%s" to be a valid composer.json file but an error has been found: %s',
                    $composerFile,
                    $exception->getMessage()
                ),
                0,
                $exception->getPrevious()
            );
        }

        if (!array_key_exists('config', $config)) {
            return 'vendor';
        }

        if (!array_key_exists('vendor-dir', $config['config'])) {
            return 'vendor';
        }

        return $config['config']['vendor-dir'];
    }

    /**
     * @param string $composerLockFile
     * @param string $composerLockFileContents
     *
     * @return string[] Names of the dev packages
     */
    private static function getDevPackageNames(string $composerLockFile, string $composerLockFileContents): array
    {
        try {
            $config = (new Json())->decode($composerLockFileContents, true);
        } catch (ParsingException $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected the file "%s" to be a valid composer.json file but an error has been found: %s',
                    $composerLockFile,
                    $exception->getMessage()
                ),
                0,
                $exception->getPrevious()
            );
        }

        if (!array_key_exists('packages-dev', $config)) {
            return [];
        }

        return array_map(
            function (array $package): string {
                return $package['name'];
            },
            $config['packages-dev']
        );
    }
}
