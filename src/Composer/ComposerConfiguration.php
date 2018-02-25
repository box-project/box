<?php

declare(strict_types=1);

namespace KevinGH\Box\Composer;

use function array_map;
use Assert\Assertion;
use const DIRECTORY_SEPARATOR;
use function dirname;
use function file_exists;
use function KevinGH\Box\FileSystem\canonicalize;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\make_path_absolute;
use function KevinGH\Box\FileSystem\make_path_relative;
use KevinGH\Box\Json\Json;
use function realpath;

final class ComposerConfiguration
{
    /**
     * Attempts to locate the `composer.json` and `composer.lock` files in the provided base-path in order to collect
     * all the dev packages.
     *
     * @param string $basePath
     *
     * @return string[] Dev package paths
     */
    public static function retrieveDevPackages(string $basePath): array
    {
        Assertion::directory($basePath);

        $composerFile = make_path_absolute('composer.json', $basePath);
        $composerLockFile = make_path_absolute('composer.lock', $basePath);

        if (file_exists($composerFile)) {
            Assertion::file($composerFile, 'Expected "%s" to be a file. Directory or link found.');
            Assertion::file($composerLockFile, 'Expected "%s" to exists. The file is either missing or a ditectory/link has been found instead.');

            $composerFileContents = file_contents($composerFile);
            $composerLockFileContents = file_contents($composerLockFile);

            return self::getDevPackagePaths($basePath, $composerFileContents, $composerLockFileContents);
        }

        return [];
    }

    /**
     * @param string $basePath
     * @param string $composerFileContents
     * @param string $composerLockFileContents
     *
     * @return string[] Dev packages paths
     */
    private static function getDevPackagePaths(
        string $basePath,
        string $composerFileContents,
        string $composerLockFileContents
    ): array
    {
        $vendorDir = make_path_absolute(
            self::getVendorDir($composerFileContents),
            $basePath
        );

        $packageNames = self::getDevPackageNames($composerLockFileContents);

        return array_map(
            function (string $packageName) use ($vendorDir): string {
                return realpath($vendorDir.DIRECTORY_SEPARATOR.$packageName);
            },
            $packageNames
        );
    }

    private static function getVendorDir(string $composerFileContents): string
    {
        $config = (new Json())->decode($composerFileContents, true);

        if (!array_key_exists('config', $config)) {
            return 'vendor';
        }

        if (!array_key_exists('vendor-dir', $config['config'])) {
            return 'vendor';
        }

        return $config['config']['vendor-dir'];
    }

    /**
     * @param string $composerLockFileContents
     *
     * @return string[] Names of the dev packages
     */
    private static function getDevPackageNames(string $composerLockFileContents): array
    {
        $config = (new Json())->decode($composerLockFileContents, true);

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