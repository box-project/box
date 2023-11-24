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
use function key;

/**
 * @private
 */
final class RequiredItem
{
    private const POLYFILL_MAP = [
        'paragonie/sodium_compat' => 'libsodium',
        'phpseclib/mcrypt_compat' => 'mcrypt',
    ];

    private const SYMFONY_POLYFILL_REGEX = '/symfony\/polyfill-(?<extension>.+)/';

    /**
     * @param array<string, string> $packageInfo
     */
    public function __construct(private readonly array $packageInfo)
    {
    }

    public function getName(): string
    {
        return key($this->packageInfo);
    }

    /**
     * @return list<string>
     */
    public function getRequiredExtensions(): array
    {
        return PackageInfo::parseExtensions($this->packageInfo);
    }

    public function getPolyfilledExtension(): ?string
    {
        $name = $this->getName();

        if (array_key_exists($name, self::POLYFILL_MAP)) {
            return self::POLYFILL_MAP[$name];
        }

        if (1 !== preg_match(self::SYMFONY_POLYFILL_REGEX, $name, $matches)) {
            return null;
        }

        $extension = $matches['extension'];

        return str_starts_with($extension, 'php') ? null : $extension;
    }
}
