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

use Phar;
use function array_diff_key;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function preg_match;
use function sprintf;

/**
 * Collect the list of requirements for running the application.
 *
 * @private
 */
final class AppRequirementsFactory
{
    private const SELF_PACKAGE = '__APPLICATION__';

    /**
     * @return list<Requirement> Serialized configured requirements
     */
    public static function create(
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
        ?int                $compressionAlgorithm,
    ): array
    {
        return self::configureExtensionRequirements(
            self::retrievePhpVersionRequirements($composerJson, $composerLock),
            $composerJson,
            $composerLock,
            $compressionAlgorithm,
        );
    }

    private static function retrievePhpVersionRequirements(
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
    ): array {
        // If the application config is set, it is the authority.
        return $composerJson->hasRequiredPhpVersion() || $composerLock->hasRequiredPhpVersion()
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

        return [Requirement::forPHP((string) $requiredPhpVersion, null)];
    }

    /**
     * @return list<Requirement>
     */
    private static function retrievePHPRequirementFromPackages(DecodedComposerLock $composerLock): array
    {
        return array_values(
            array_map(
                static fn (PackageInfo $packageInfo) => Requirement::forPHP(
                    (string) $packageInfo->getRequiredPhpVersion(),
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
        array               $requirements,
        DecodedComposerJson $composerJson,
        DecodedComposerLock $composerLock,
        ?int                $compressionAlgorithm,
    ): array {
        $extensionRequirements = self::collectExtensionRequirements(
            $composerJson,
            $composerLock,
            $compressionAlgorithm,
        );

        foreach ($extensionRequirements as $extension => $packages) {
            foreach ($packages as $package) {
                if (self::SELF_PACKAGE === $package) {
                    $message = sprintf(
                        'The application requires the extension "%s". Enable it or install a polyfill.',
                        $extension,
                    );
                    $helpMessage = sprintf(
                        'The application requires the extension "%s".',
                        $extension,
                    );
                } else {
                    $message = sprintf(
                        'The package "%s" requires the extension "%s". Enable it or install a polyfill.',
                        $package,
                        $extension,
                    );
                    $helpMessage = sprintf(
                        'The package "%s" requires the extension "%s".',
                        $package,
                        $extension,
                    );
                }

                $requirements[] = [
                    'type' => 'extension',
                    'condition' => $extension,
                    'message' => $message,
                    'helpMessage' => $helpMessage,
                ];
            }
        }

        return $requirements;
    }

    /**
     * Collects the extension required. It also accounts for the polyfills, i.e. if the polyfill
     * `symfony/polyfill-mbstring` is provided then the extension `ext-mbstring` will not be required.
     *
     * @return array Associative array containing the list of extensions required
     */
    private static function collectExtensionRequirements(
        array $composerJsonContents,
        array $composerLockContents,
        ?int $compressionAlgorithm,
    ): array {
        $requirements = [];
        $polyfills = [];

        if (Phar::BZ2 === $compressionAlgorithm) {
            $requirements['bz2'] = [self::SELF_PACKAGE];
        }

        if (Phar::GZ === $compressionAlgorithm) {
            $requirements['zlib'] = [self::SELF_PACKAGE];
        }

        $platform = $composerLockContents['platform'] ?? [];

        foreach ($platform as $package => $constraint) {
            if (preg_match('/^ext-(?<extension>.+)$/', (string) $package, $matches)) {
                $extension = $matches['extension'];

                $requirements[$extension] = [self::SELF_PACKAGE];
            }
        }

        [$polyfills, $requirements] = [] === $composerLockContents
            ? self::collectComposerJsonExtensionRequirements($composerJsonContents, $polyfills, $requirements)
            : self::collectComposerLockExtensionRequirements($composerLockContents, $polyfills, $requirements);

        return array_diff_key($requirements, $polyfills);
    }

    private static function collectComposerJsonExtensionRequirements(array $composerJsonContents, $polyfills, $requirements): array
    {
        $packages = $composerJsonContents['require'] ?? [];

        foreach ($packages as $packageName => $constraint) {
            if (1 === preg_match('/symfony\/polyfill-(?<extension>.+)/', (string) $packageName, $matches)) {
                $extension = $matches['extension'];

                if (!str_starts_with($extension, 'php')) {
                    $polyfills[$extension] = true;

                    continue;
                }
            }

            if ('paragonie/sodium_compat' === $packageName) {
                $polyfills['libsodium'] = true;

                continue;
            }

            if ('phpseclib/mcrypt_compat' === $packageName) {
                $polyfills['mcrypt'] = true;

                continue;
            }

            if ('php' !== $packageName && preg_match('/^ext-(?<extension>.+)$/', (string) $packageName, $matches)) {
                $requirements[$matches['extension']] = [self::SELF_PACKAGE];
            }
        }

        return [$polyfills, $requirements];
    }

    private static function collectComposerLockExtensionRequirements(array $composerLockContents, $polyfills, $requirements): array
    {
        $packages = $composerLockContents['packages'] ?? [];

        foreach ($packages as $packageInfo) {
            $packageRequire = $packageInfo['require'] ?? [];

            if (1 === preg_match('/symfony\/polyfill-(?<extension>.+)/', (string) $packageInfo['name'], $matches)) {
                $extension = $matches['extension'];

                if (!str_starts_with((string) $extension, 'php')) {
                    $polyfills[$extension] = true;
                }
            }

            if ('paragonie/sodium_compat' === $packageInfo['name']) {
                $polyfills['libsodium'] = true;
            }

            if ('phpseclib/mcrypt_compat' === $packageInfo['name']) {
                $polyfills['mcrypt'] = true;
            }

            foreach ($packageRequire as $package => $constraint) {
                if (1 === preg_match('/^ext-(?<extension>.+)$/', (string) $package, $matches)) {
                    $extension = $matches['extension'];

                    if (false === array_key_exists($extension, $requirements)) {
                        $requirements[$extension] = [];
                    }

                    $requirements[$extension][] = $packageInfo['name'];
                }
            }
        }

        return [$polyfills, $requirements];
    }
}
