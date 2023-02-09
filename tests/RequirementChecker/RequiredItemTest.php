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

use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\RequirementChecker\RequiredItem
 *
 * @internal
 */
final class RequiredItemTest extends TestCase
{
    /**
     * @dataProvider packageInfoProvider
     */
    public function test_it_can_parse_the_decoded_data(
        array $rawPackageInfo,
        string $expectedName,
        array $expectedRequiredExtensions,
        ?string $expectedPolyfilledExtension,
    ): void {
        $requiredItem = new RequiredItem($rawPackageInfo);

        self::assertStateIs(
            $requiredItem,
            $expectedName,
            $expectedRequiredExtensions,
            $expectedPolyfilledExtension,
        );
    }

    public static function packageInfoProvider(): iterable
    {
        yield 'nominal' => [
            ['box/test' => '^7.1'],
            'box/test',
            [],
            null,
        ];

        yield 'PHP requirement' => [
            ['php' => '^8.2'],
            'php',
            [],
            null,
        ];

        yield 'extension requirement' => [
            ['ext-json' => '*'],
            'ext-json',
            ['json'],
            null,
        ];

        yield 'Symfony mbstring polyfill' => [
            ['symfony/polyfill-mbstring' => '^7.1'],
            'symfony/polyfill-mbstring',
            [],
            'mbstring',
        ];

        yield 'phpseclib/mcrypt_compat' => [
            ['phpseclib/mcrypt_compat' => '*'],
            'phpseclib/mcrypt_compat',
            [],
            'mcrypt',
        ];
    }

    private static function assertStateIs(
        RequiredItem $actual,
        string $expectedName,
        array $expectedRequiredExtensions,
        ?string $expectedPolyfilledExtension,
    ): void {
        self::assertSame($expectedName, $actual->getName());
        self::assertSame($expectedRequiredExtensions, $actual->getRequiredExtensions());
        self::assertSame($expectedPolyfilledExtension, $actual->getPolyfilledExtension());
    }
}
