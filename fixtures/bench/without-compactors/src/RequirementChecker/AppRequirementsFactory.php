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

use BenchTest\Phar\CompressionAlgorithm;
use function array_diff_key;
use function array_filter;
use function array_map;
use function array_merge_recursive;
use function array_values;

/**
 * Collect the list of requirements for running the application.
 *
 * @private
 */
final class AppRequirementsFactory
{
    private const SELF_PACKAGE = '__APPLICATION__';

    /**
     * @return list<Requirement> Configured requirements
     */
    public static function create(
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): array {
        return self::configureExtensionRequirements(
            self::retrievePhpVersionRequirements($composerJson, $composerLock),
            $composerJson,
            $composerLock,
            $compressionAlgorithm,
        );
    }

    /**
     * @return list<Requirement>
     */
    private static function retrievePhpVersionRequirements(
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
    ): array {
        // If the application has a constraint on the PHP version, it is the authority.
        return $composerLock->hasRequiredPhpVersion() || $composerJson->hasRequiredPhpVersion()
            ? self::retrievePHPRequirementFromPlatform($composerJson, $composerLock)
            : self::retrievePHPRequirementFromPackages($composerLock);
    }

    /**
     * @return list<Requirement>
     */
    private static function retrievePHPRequirementFromPlatform(
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
    ): array {
        $requiredPhpVersion = $composerLock->getRequiredPhpVersion() ?? $composerJson->getRequiredPhpVersion();

        return null === $requiredPhpVersion ? [] : [Requirement::forPHP($requiredPhpVersion, null)];
    }

    /**
     * @return list<Requirement>
     */
    private static function retrievePHPRequirementFromPackages(DecodedComposerLock $composerLock): array
    {
        return array_values(
            array_map(
                static fn (PackageInfo $packageInfo) => Requirement::forPHP(
                    $packageInfo->getRequiredPhpVersion(),
                    $packageInfo->getName(),
                ),
                array_filter(
                    $composerLock->getPackages(),
                    static fn (PackageInfo $packageInfo) => $packageInfo->hasRequiredPhpVersion(),
                ),
            ),
        );
    }

    /**
     * @param list<Requirement> $requirements
     */
    private static function configureExtensionRequirements(
        array $requirements,
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): array {
        [$extensionRequirements, $extensionConflicts] = self::collectExtensionRequirements(
            $composerJson,
            $composerLock,
            $compressionAlgorithm,
        );

        foreach ($extensionRequirements as $extension => $packages) {
            foreach ($packages as $package) {
                $requirements[] = Requirement::forRequiredExtension(
                    $extension,
                    self::SELF_PACKAGE === $package ? null : $package,
                );
            }
        }

        foreach ($extensionConflicts as $extension => $packages) {
            foreach ($packages as $package) {
                $requirements[] = Requirement::forConflictingExtension(
                    $extension,
                    self::SELF_PACKAGE === $package ? null : $package,
                );
            }
        }

        return $requirements;
    }

    /**
     * Collects the extension required. It also accounts for the polyfills, i.e. if the polyfill
     * `symfony/polyfill-mbstring` is provided then the extension `ext-mbstring` will not be required.
     *
     * @return array{array<string, list<string>>, array<string, list<string>>}
     */
    private static function collectExtensionRequirements(
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): array {
        $requirements = [];

        $compressionAlgorithmRequiredExtension = $compressionAlgorithm->getRequiredExtension();

        if (null !== $compressionAlgorithmRequiredExtension) {
            $requirements[$compressionAlgorithmRequiredExtension] = [self::SELF_PACKAGE];
        }

        foreach ($composerLock->getPlatformExtensions() as $extension) {
            $requirements[$extension] = [self::SELF_PACKAGE];
        }

        // If the lock is present it is the authority. If not fallback on the .json. It is pointless to check both
        // since they will contain redundant information.
        [$polyfills, $requirements, $conflicts] = $composerLock->isEmpty()
            ? self::collectComposerJsonExtensionRequirements($composerJson, $requirements)
            : self::collectComposerLockExtensionRequirements($composerLock, $requirements);

        $jsonConflicts = self::collectComposerJsonExtensionRequirements($composerJson, $requirements)[2];

        return [
            array_diff_key($requirements, $polyfills),
            array_merge_recursive($conflicts, $jsonConflicts),
        ];
    }

    /**
     * @param array<string, list<string>> $requirements The key is the extension name and the value the list of sources (app literal string or the package name).
     *
     * @return array{array<string, true>, array<string, list<string>>, array<string, list<string>>}
     */
    private static function collectComposerJsonExtensionRequirements(
        DecodedComposerJson $composerJson,
        array $requirements,
    ): array {
        $polyfills = [];
        $conflicts = [];

        foreach ($composerJson->getRequiredItems() as $packageInfo) {
            $polyfilledExtension = $packageInfo->getPolyfilledExtension();

            if (null !== $polyfilledExtension) {
                $polyfills[$polyfilledExtension] = true;

                continue;
            }

            foreach ($packageInfo->getRequiredExtensions() as $extension) {
                $requirements[$extension] = [self::SELF_PACKAGE];
            }
        }

        foreach ($composerJson->getConflictingExtensions() as $extension) {
            $conflicts[$extension] = [self::SELF_PACKAGE];
        }

        return [
            $polyfills,
            $requirements,
            $conflicts,
        ];
    }

    /**
     * @param array<string, list<string>> $requirements The key is the extension name and the value the list of sources (app literal string or the package name).
     *
     * @return array{array<string, true>, array<string, list<string>>, array<string, list<string>>}
     */
    private static function collectComposerLockExtensionRequirements(
        DecodedComposerLock $composerLock,
        array $requirements,
    ): array {
        $polyfills = [];
        $conflicts = [];

        foreach ($composerLock->getPackages() as $packageInfo) {
            foreach ($packageInfo->getPolyfilledExtensions() as $polyfilledExtension) {
                $polyfills[$polyfilledExtension] = true;
            }

            foreach ($packageInfo->getRequiredExtensions() as $extension) {
                $requirements[$extension][] = $packageInfo->getName();
            }

            foreach ($packageInfo->getConflictingExtensions() as $extension) {
                $conflicts[$extension][] = $packageInfo->getName();
            }
        }

        return [$polyfills, $requirements, $conflicts];
    }
}
