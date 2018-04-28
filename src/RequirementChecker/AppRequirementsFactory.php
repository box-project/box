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

use function array_diff_key;
use function array_key_exists;
use function sprintf;
use function substr;

/**
 * Collect the list of requirements for running the application.
 *
 * @private
 */
final class AppRequirementsFactory
{
    private const SELF_PACKAGE = '__APPLICATION__';

    /**
     * @param array $composerLockDecodedContents Decoded JSON contents of the `composer.lock` file
     *
     * @return array Serialized configured requirements
     */
    public static function create(array $composerLockDecodedContents, bool $compressed): array
    {
        return self::configureExtensionRequirements(
            self::configurePhpVersionRequirements([], $composerLockDecodedContents),
            $composerLockDecodedContents,
            $compressed
        );
    }

    private static function configurePhpVersionRequirements(array $requirements, array $composerLockContents): array
    {
        if (isset($composerLockContents['platform']['php'])) {
            $requiredPhpVersion = $composerLockContents['platform']['php'];

            $requirements[] = [
                self::generatePhpCheckStatement((string) $requiredPhpVersion),
                sprintf(
                    'The application requires the version "%s" or greater.',
                    $requiredPhpVersion
                ),
                sprintf(
                    'The application requires the version "%s" or greater.',
                    $requiredPhpVersion
                ),
            ];

            return $requirements; // No need to check the packages requirements: the application platform config is the authority here
        }

        $packages = $composerLockContents['packages'] ?? [];

        foreach ($packages as $packageInfo) {
            $requiredPhpVersion = $packageInfo['require']['php'] ?? null;

            if (null === $requiredPhpVersion) {
                continue;
            }

            $requirements[] = [
                self::generatePhpCheckStatement((string) $requiredPhpVersion),
                sprintf(
                    'The package "%s" requires the version "%s" or greater.',
                    $packageInfo['name'],
                    $requiredPhpVersion
                ),
                sprintf(
                    'The package "%s" requires the version "%s" or greater.',
                    $packageInfo['name'],
                    $requiredPhpVersion
                ),
            ];
        }

        return $requirements;
    }

    private static function configureExtensionRequirements(array $requirements, array $composerLockContents, bool $compressed): array
    {
        $extensionRequirements = self::collectExtensionRequirements($composerLockContents, $compressed);

        foreach ($extensionRequirements as $extension => $packages) {
            foreach ($packages as $package) {
                if (self::SELF_PACKAGE === $package) {
                    $message = sprintf(
                        'The application requires the extension "%s". Enable it or install a polyfill.',
                        $extension
                    );
                    $helpMessage = sprintf(
                        'The application requires the extension "%s".',
                        $extension
                    );
                } else {
                    $message = sprintf(
                        'The package "%s" requires the extension "%s". Enable it or install a polyfill.',
                        $package,
                        $extension
                    );
                    $helpMessage = sprintf(
                        'The package "%s" requires the extension "%s".',
                        $package,
                        $extension
                    );
                }

                $requirements[] = [
                    self::generateExtensionCheckStatement($extension),
                    $message,
                    $helpMessage,
                ];
            }
        }

        return $requirements;
    }

    /**
     * Collects the extension required. It also accounts for the polyfills, i.e. if the polyfill `symfony/polyfill-mbstring` is provided
     * then the extension `ext-mbstring` will not be required.
     *
     * @param array $composerLockContents
     *
     * @return array Associative array containing the list of extensions required
     */
    private static function collectExtensionRequirements(array $composerLockContents, bool $compressed): array
    {
        $requirements = [];
        $polyfills = [];

        if ($compressed) {
            $requirements['zip'] = [self::SELF_PACKAGE];
        }

        $platform = $composerLockContents['platform'] ?? [];

        foreach ($platform as $package => $constraint) {
            if (preg_match('/^ext-(?<extension>.+)$/', $package, $matches)) {
                $extension = $matches['extension'];

                $requirements[$extension] = [self::SELF_PACKAGE];
            }
        }

        $packages = $composerLockContents['packages'] ?? [];

        foreach ($packages as $packageInfo) {
            $packageRequire = $packageInfo['require'] ?? [];

            if (1 === preg_match('/symfony\/polyfill-(?<extension>.+)/', $packageInfo['name'], $matches)) {
                $extension = $matches['extension'];

                if ('php' !== substr($extension, 0, 3)) {
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
                if (1 === preg_match('/^ext-(?<extension>.+)$/', $package, $matches)) {
                    $extension = $matches['extension'];

                    if (false === array_key_exists($extension, $requirements)) {
                        $requirements[$extension] = [];
                    }

                    $requirements[$extension][] = $packageInfo['name'];
                }
            }
        }

        return array_diff_key($requirements, $polyfills);
    }

    private static function generatePhpCheckStatement(string $requiredPhpVersion): string
    {
        return <<<PHP
require_once __DIR__.'/../vendor/composer/semver/src/Semver.php';
require_once __DIR__.'/../vendor/composer/semver/src/VersionParser.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/ConstraintInterface.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/EmptyConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/MultiConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/Constraint.php';

return \Composer\Semver\Semver::satisfies(
    sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION),
    '$requiredPhpVersion'
);

PHP;
    }

    private static function generateExtensionCheckStatement(string $extension): string
    {
        return "return \\extension_loaded('$extension');";
    }
}
