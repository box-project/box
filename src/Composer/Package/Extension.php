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

use Stringable;
use function array_key_exists;
use function str_starts_with;

final class Extension implements Stringable
{
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

    public static function tryToParse(string $packageName): ?self
    {
        if (!str_starts_with($packageName, 'ext-')) {
            return null;
        }

        $extension = mb_substr($packageName, 4);

        return new self(self::EXTENSION_NAME_MAP[$extension] ?? $extension);
    }

    public static function tryToParsePolyfill(string $packageName): ?self
    {
        if (array_key_exists($packageName, self::POLYFILL_MAP)) {
            return new self(self::POLYFILL_MAP[$packageName]);
        }

        if (1 !== preg_match(self::SYMFONY_POLYFILL_REGEX, $packageName, $matches)) {
            return null;
        }

        $extension = $matches['extension'];

        if (str_starts_with($extension, 'php')) {
            return null;
        }

        return new self($extension);
    }

    public static function isExtension(string $packageName): bool
    {
        return str_starts_with($packageName, 'ext-');
    }

    public static function isExtensionPolyfill(string $packageName): bool
    {
        if (array_key_exists($packageName, self::POLYFILL_MAP)) {
            return true;
        }

        if (1 === preg_match(self::SYMFONY_POLYFILL_REGEX, $packageName, $matches)) {
            $extension = $matches['extension'];

            return !str_starts_with($extension, 'php');
        }

        return false;
    }

    public function __construct(
        public string $name,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
