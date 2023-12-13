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

namespace KevinGH\Box\Composer\Package;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;

/**
 * @private
 */
final readonly class PackageInfo
{
    public function __construct(private array $packageInfo)
    {
    }

    public function getName(): string
    {
        return $this->packageInfo['name'];
    }

    public function getRequiredPhpVersion(): ?string
    {
        return $this->packageInfo['require']['php'] ?? null;
    }

    public function hasRequiredPhpVersion(): bool
    {
        return null !== $this->getRequiredPhpVersion();
    }

    public function getRequiredExtensions(): Extensions
    {
        return self::parseExtensions($this->packageInfo['require'] ?? []);
    }

    public function getPolyfilledExtensions(): Extensions
    {
        if (array_key_exists('provide', $this->packageInfo)) {
            return self::parseExtensions($this->packageInfo['provide']);
        }

        // TODO: remove the following code in 5.0.
        $packageName = $this->packageInfo['name'];
        $polyfilledExtension = Extension::tryToParsePolyfill($packageName);

        return new Extensions(
            null === $polyfilledExtension
                ? []
                : [$polyfilledExtension],
        );
    }

    public function getConflictingExtensions(): Extensions
    {
        return self::parseExtensions($this->packageInfo['conflict'] ?? []);
    }

    /**
     * @param array<string, string> $constraints
     */
    public static function parseExtensions(array $constraints): Extensions
    {
        return new Extensions(
            array_values(
                array_filter(
                    array_map(
                        Extension::tryToParse(...),
                        array_keys($constraints),
                    ),
                    static fn (?Extension $extension) => null !== $extension,
                ),
            ),
        );
    }
}
