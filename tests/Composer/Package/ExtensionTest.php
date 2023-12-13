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

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Extension::class)]
final class ExtensionTest extends TestCase
{
    #[DataProvider('extensionPackageNameProvider')]
    public function test_it_can_say_if_a_composer_package_name_is_an_extension(
        string $packageName,
        bool $expected,
    ): void {
        $actual = Extension::isExtension($packageName);

        self::assertSame($expected, $actual);
    }

    public static function extensionPackageNameProvider(): iterable
    {
        foreach (self::extensionProvider() as $title => [$packageName, $extensionOrException]) {
            yield $title => [
                $packageName,
                $extensionOrException instanceof Extension,
            ];
        }
    }

    #[DataProvider('extensionProvider')]
    public function test_it_can_parse_an_extension_name(
        string $packageName,
        ?Extension $expected,
    ): void {
        if ($expected instanceof Exception) {
            $this->expectExceptionObject($expected);
        }

        $actual = Extension::tryToParse($packageName);

        self::assertEquals($expected, $actual);
    }

    public static function extensionProvider(): iterable
    {
        yield 'extension package' => [
            'ext-http',
            new Extension('http'),
        ];

        yield 'not an extension package' => [
            'laminas/laminas-code',
            null,
        ];

        yield 'not an extension package (confusing case)' => [
            'ext/http',
            null,
        ];

        yield 'Zend opcache extension' => [
            'ext-zend-opcache',
            new Extension('zend opcache'),
        ];
    }

    #[DataProvider('polyfillPackageNameProvider')]
    public function test_it_can_say_if_a_composer_package_name_is_a_polyfill_for_an_extension(
        string $packageName,
        bool $expected,
    ): void {
        $actual = Extension::isExtensionPolyfill($packageName);

        self::assertSame($expected, $actual);
    }

    public static function polyfillPackageNameProvider(): iterable
    {
        foreach (self::polyfillExtensionProvider() as $title => [$packageName, $extensionOrException]) {
            yield $title => [
                $packageName,
                $extensionOrException instanceof Extension,
            ];
        }
    }

    #[DataProvider('polyfillExtensionProvider')]
    public function test_it_can_parse_an_extension_from_an_extension_polyfill_package_name(
        string $packageName,
        ?Extension $expected,
    ): void {
        $actual = Extension::tryToParsePolyfill($packageName);

        self::assertEquals($expected, $actual);
    }

    public static function polyfillExtensionProvider(): iterable
    {
        yield 'sodium polyfill' => [
            'paragonie/sodium_compat',
            new Extension('libsodium'),
        ];

        yield 'not an extension package' => [
            'laminas/laminas-code',
            null,
        ];

        yield 'extension name' => [
            'ext-http',
            null,
        ];

        yield 'Symfony extension polyfill' => [
            'symfony/polyfill-mbstring',
            new Extension('mbstring'),
        ];

        yield 'Symfony PHP polyfill' => [
            'symfony/polyfill-php72',
            null,
        ];
    }

    #[DataProvider('stringExtensionProvider')]
    public function test_it_is_stringeable(
        Extension $extension,
        string $expected,
    ): void {
        $actual = $extension->__toString();

        self::assertSame($expected, $actual);
    }

    public static function stringExtensionProvider(): iterable
    {
        yield 'nominal' => [
            new Extension('http'),
            'http',
        ];

        yield 'Zend opcache' => [
            new Extension('zend opcache'),
            'zend opcache',
        ];
    }
}
