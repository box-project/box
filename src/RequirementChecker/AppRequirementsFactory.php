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

use KevinGH\Box\Composer\Artifact\DecodedComposerJson;
use KevinGH\Box\Composer\Artifact\DecodedComposerLock;
use KevinGH\Box\Composer\Package\Extension;
use KevinGH\Box\Composer\Package\PackageInfo;
use KevinGH\Box\Phar\CompressionAlgorithm;
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

    public static function create(
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): Requirements {
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
    ): Requirements {
        $extensions = self::collectExtensionRequirements(
            $composerJson,
            $composerLock,
            $compressionAlgorithm,
        );

        foreach ($extensions->getRequiredExtensions() as $extensionName => $packageNames) {
            foreach ($packageNames as $packageName) {
                $requirements[] = Requirement::forRequiredExtension(
                    $extensionName,
                    self::SELF_PACKAGE === $packageName ? null : $packageName,
                );
            }
        }

        foreach ($extensions->getConflictingExtensions() as $extensionName => $packageNames) {
            foreach ($packageNames as $packageName) {
                $requirements[] = Requirement::forConflictingExtension(
                    $extensionName,
                    self::SELF_PACKAGE === $packageName ? null : $packageName,
                );
            }
        }

        return new Requirements($requirements);
    }

    /**
     * Collects the extension required. It also accounts for the polyfills, i.e. if the polyfill
     * `symfony/polyfill-mbstring` is provided then the extension `ext-mbstring` will not be required.
     */
    private static function collectExtensionRequirements(
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): ExtensionRegistry {
        $extensions = new ExtensionRegistry();

        $compressionAlgorithmRequiredExtension = $compressionAlgorithm->getRequiredExtension();

        if (null !== $compressionAlgorithmRequiredExtension) {
            $extensions->addRequiredExtension(
                new Extension($compressionAlgorithmRequiredExtension),
                self::SELF_PACKAGE,
            );
        }

        foreach ($composerLock->getPlatformExtensions() as $extension) {
            $extensions->addRequiredExtension($extension, self::SELF_PACKAGE);
        }

        // If the lock is present it is the authority. If not fallback on the .json. It is pointless to check both
        // since they will contain redundant information.
        // TODO: to check but I think we should check the installed.json file instead
        // This would allow to avoid to have to care about the case where the .lock may
        // not be present for whatever reason.
        self::collectComposerLockExtensionRequirements($composerLock, $extensions);
        self::collectComposerJsonExtensionRequirements($composerJson, $extensions);

        return $extensions;
    }

    private static function collectComposerJsonExtensionRequirements(
        DecodedComposerJson $composerJson,
        ExtensionRegistry $extensionRegistry,
    ): void {
        // TODO: check that the extensions provided by the package itself are accounted for.
        // TODO: as we add the constraints, check that we do not override the already registered constraints.
        foreach ($composerJson->getRequiredItems() as $packageInfo) {
            $polyfilledExtension = $packageInfo->getPolyfilledExtension();

            if (null !== $polyfilledExtension) {
                $extensionRegistry->addProvidedExtension($polyfilledExtension, self::SELF_PACKAGE);

                continue;
            }

            foreach ($packageInfo->getRequiredExtensions() as $extension) {
                $extensionRegistry->addRequiredExtension(
                    $extension,
                    self::SELF_PACKAGE,
                );
            }
        }

        foreach ($composerJson->getConflictingExtensions() as $extension) {
            $extensionRegistry->addConflictingExtension(
                $extension,
                self::SELF_PACKAGE,
            );
        }
    }

    private static function collectComposerLockExtensionRequirements(
        DecodedComposerLock $composerLock,
        ExtensionRegistry $extensions,
    ): void {
        foreach ($composerLock->getPackages() as $packageInfo) {
            foreach ($packageInfo->getPolyfilledExtensions() as $polyfilledExtension) {
                $extensions->addProvidedExtension(
                    $polyfilledExtension,
                    $packageInfo->getName(),
                );
            }

            foreach ($packageInfo->getRequiredExtensions() as $extension) {
                $extensions->addRequiredExtension(
                    $extension,
                    $packageInfo->getName(),
                );
            }

            foreach ($packageInfo->getConflictingExtensions() as $extension) {
                $extensions->addConflictingExtension(
                    $extension,
                    $packageInfo->getName(),
                );
            }
        }
    }
}
