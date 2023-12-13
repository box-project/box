<?php

declare(strict_types=1);

namespace KevinGH\Box\Composer\Package;

use KevinGH\Box\Composer\Throwable\InvalidExtensionName;
use Stringable;
use function array_key_exists;
use function sprintf;
use function str_starts_with;
use function substr;

final class Extension implements Stringable
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

    public static function parse(string $packageName): self
    {
        if (1 !== preg_match(self::EXTENSION_REGEX, $packageName, $matches)) {
            throw InvalidExtensionName::forName($packageName);
        }

        $extension = $matches['extension'];

        return new self(self::EXTENSION_NAME_MAP[$extension] ?? $extension);
    }

    public static function parsePolyfill(string $packageName): self
    {
        if (array_key_exists($packageName, self::POLYFILL_MAP)) {
            return new self(self::POLYFILL_MAP[$packageName]);
        }

        if (1 !== preg_match(self::SYMFONY_POLYFILL_REGEX, $packageName, $matches)) {
            throw InvalidExtensionName::forPolyfillPackage($packageName);
        }

        $extension = $matches['extension'];

        if (str_starts_with($extension, 'php')) {
            throw InvalidExtensionName::forPolyfillPackage($packageName);
        }

        return new self($extension);
    }

    public static function isExtension(string $packageName): bool
    {
        return 1 === preg_match(self::EXTENSION_REGEX, $packageName, $matches);
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