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

use function key;

/**
 * @private
 */
final readonly class RequiredItem
{
    private const POLYFILL_MAP = [
        'paragonie/sodium_compat' => 'libsodium',
        'phpseclib/mcrypt_compat' => 'mcrypt',
    ];

    private const SYMFONY_POLYFILL_REGEX = '/symfony\/polyfill-(?<extension>.+)/';

    /**
     * @param array<string, string> $packageInfo
     */
    public function __construct(private array $packageInfo)
    {
    }

    public function getName(): string
    {
        return key($this->packageInfo);
    }

    public function getRequiredExtensions(): Extensions
    {
        return PackageInfo::parseExtensions($this->packageInfo);
    }

    public function getPolyfilledExtension(): ?Extension
    {
        $name = $this->getName();

        return Extension::tryToParsePolyfill($name);
    }
}
