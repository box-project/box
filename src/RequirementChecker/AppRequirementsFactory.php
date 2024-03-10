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

use KevinGH\Box\Composer\Artifact\ComposerJson;
use KevinGH\Box\Composer\Artifact\ComposerLock;
use KevinGH\Box\Composer\Package\Extension;
use KevinGH\Box\Phar\CompressionAlgorithm;

/**
 * Collect the list of requirements for running the application.
 *
 * @private
 */
final class AppRequirementsFactory
{
    private const SELF_PACKAGE = null;

    public function createUnfiltered(
        ComposerJson $composerJson,
        ComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): Requirements {
        return $this
            ->createBuilder(
                $composerJson,
                $composerLock,
                $compressionAlgorithm,
            )
            ->all();
    }

    public function create(
        ComposerJson $composerJson,
        ComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): Requirements {
        return $this
            ->createBuilder(
                $composerJson,
                $composerLock,
                $compressionAlgorithm,
            )
            ->build();
    }

    private function createBuilder(
        ComposerJson $composerJson,
        ComposerLock $composerLock,
        CompressionAlgorithm $compressionAlgorithm,
    ): RequirementsBuilder {
        $requirementsBuilder = new RequirementsBuilder();

        self::retrievePhpVersionRequirements($requirementsBuilder, $composerJson, $composerLock);
        self::collectExtensionRequirementsFromCompressionAlgorithm($requirementsBuilder, $compressionAlgorithm);
        self::collectComposerLockExtensionRequirements($composerLock, $requirementsBuilder);
        self::collectComposerJsonExtensionRequirements($composerJson, $requirementsBuilder);

        return $requirementsBuilder;
    }

    private static function retrievePhpVersionRequirements(
        RequirementsBuilder $requirementsBuilder,
        ComposerJson $composerJson,
        ComposerLock $composerLock,
    ): void {
        // If the application has a constraint on the PHP version, it is the authority.
        $composerLock->hasRequiredPhpVersion() || $composerJson->hasRequiredPhpVersion()
            ? self::retrievePHPRequirementFromPlatform($requirementsBuilder, $composerJson, $composerLock)
            : self::retrievePHPRequirementFromPackages($requirementsBuilder, $composerLock);
    }

    private static function retrievePHPRequirementFromPlatform(
        RequirementsBuilder $requirementsBuilder,
        ComposerJson $composerJson,
        ComposerLock $composerLock,
    ): void {
        $requiredPhpVersion = $composerLock->getRequiredPhpVersion() ?? $composerJson->getRequiredPhpVersion();

        if (null !== $requiredPhpVersion) {
            $requirementsBuilder->addRequirement(
                Requirement::forPHP($requiredPhpVersion, self::SELF_PACKAGE),
            );
        }
    }

    private static function retrievePHPRequirementFromPackages(
        RequirementsBuilder $requirementsBuilder,
        ComposerLock $composerLock,
    ): void {
        foreach ($composerLock->getPackages() as $packageInfo) {
            if ($packageInfo->hasRequiredPhpVersion()) {
                $requirementsBuilder->addRequirement(
                    Requirement::forPHP(
                        $packageInfo->getRequiredPhpVersion(),
                        $packageInfo->getName(),
                    ),
                );
            }
        }
    }

    /**
     * Collects the extension required. It also accounts for the polyfills, i.e. if the polyfill
     * `symfony/polyfill-mbstring` is provided then the extension `ext-mbstring` will not be required.
     */
    private static function collectExtensionRequirementsFromCompressionAlgorithm(
        RequirementsBuilder $requirementsBuilder,
        CompressionAlgorithm $compressionAlgorithm,
    ): void {
        $compressionAlgorithmRequiredExtension = $compressionAlgorithm->getRequiredExtension();

        if (null !== $compressionAlgorithmRequiredExtension) {
            $requirementsBuilder->addRequiredExtension(
                new Extension($compressionAlgorithmRequiredExtension),
                self::SELF_PACKAGE,
            );
        }
    }

    private static function collectComposerJsonExtensionRequirements(
        ComposerJson $composerJson,
        RequirementsBuilder $requirementsBuilder,
    ): void {
        foreach ($composerJson->getRequiredItems() as $packageInfo) {
            $polyfilledExtension = $packageInfo->getPolyfilledExtension();

            if (null !== $polyfilledExtension) {
                $requirementsBuilder->addProvidedExtension($polyfilledExtension, self::SELF_PACKAGE);

                continue;
            }

            foreach ($packageInfo->getRequiredExtensions() as $extension) {
                $requirementsBuilder->addRequiredExtension(
                    $extension,
                    self::SELF_PACKAGE,
                );
            }
        }

        foreach ($composerJson->getConflictingExtensions() as $extension) {
            $requirementsBuilder->addConflictingExtension(
                $extension,
                self::SELF_PACKAGE,
            );
        }
    }

    private static function collectComposerLockExtensionRequirements(
        ComposerLock $composerLock,
        RequirementsBuilder $requirementsBuilder,
    ): void {
        foreach ($composerLock->getPlatformExtensions() as $extension) {
            $requirementsBuilder->addRequiredExtension($extension, self::SELF_PACKAGE);
        }

        foreach ($composerLock->getPackages() as $packageInfo) {
            foreach ($packageInfo->getPolyfilledExtensions() as $polyfilledExtension) {
                $requirementsBuilder->addProvidedExtension(
                    $polyfilledExtension,
                    $packageInfo->getName(),
                );
            }

            foreach ($packageInfo->getRequiredExtensions() as $extension) {
                $requirementsBuilder->addRequiredExtension(
                    $extension,
                    $packageInfo->getName(),
                );
            }

            foreach ($packageInfo->getConflictingExtensions() as $extension) {
                $requirementsBuilder->addConflictingExtension(
                    $extension,
                    $packageInfo->getName(),
                );
            }
        }
    }
}
