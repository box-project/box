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

use function array_key_exists;

/**
 * @private
 */
final class PackageInfo
{
    private const EXTENSION_REGEX = '/^ext-(?<extension>.+)$/';

    // Some extensions name differs in how they are registered in composer.json
    // and the name used when doing a `extension_loaded()` check.
    // See https://github.com/box-project/box/issues/653.
    private const EXTENSION_NAME_MAP = [
        'zend-opcache' => 'zend opcache',
    ];

    private const POLYFILL_MAP = [
        'paragonie/sodium_compat' => 'libsodium',
        'phpseclib/mcrypt_compat' => 'mcrypt',
    ];

    private const SYMFONY_POLYFILL_REGEX = '/symfony\/polyfill-(?<extension>.+)/';

    public function __construct(private readonly array $packageInfo)
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

    /**
     * @return list<string>
     */
    public function getRequiredExtensions(): array
    {
        return self::parseExtensions($this->packageInfo['require'] ?? []);
    }

    /**
     * @return list<string>
     */
    public function getPolyfilledExtensions(): array
    {
        if (array_key_exists('provide', $this->packageInfo)) {
            return self::parseExtensions($this->packageInfo['provide']);
        }

        // TODO: remove the following code in 5.0.
        $name = $this->packageInfo['name'];

        if (array_key_exists($name, self::POLYFILL_MAP)) {
            return [self::POLYFILL_MAP[$name]];
        }

        if (1 !== preg_match(self::SYMFONY_POLYFILL_REGEX, $name, $matches)) {
            return [];
        }

        $extension = $matches['extension'];

        return str_starts_with($extension, 'php') ? [] : [$extension];
    }

    /**
     * @return list<string>
     */
    public function getConflictingExtensions(): array
    {
        return array_key_exists('conflict', $this->packageInfo)
            ? self::parseExtensions($this->packageInfo['conflict'])
            : [];
    }

    /**
     * @param array<string, string> $constraints
     *
     * @return list<string>
     */
    public static function parseExtensions(array $constraints): array
    {
        $extensions = [];

        foreach ($constraints as $package => $constraint) {
            if (preg_match(self::EXTENSION_REGEX, $package, $matches)) {
                $extension = $matches['extension'];

                $extensions[] = self::EXTENSION_NAME_MAP[$extension] ?? $extension;
            }
        }

        return $extensions;
    }
}
