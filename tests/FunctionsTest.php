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

namespace KevinGH\Box;

use Phar;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class FunctionsTest extends TestCase
{
    #[DataProvider('bytesProvider')]
    public function test_it_can_format_bytes(float|int $bytes, string $expected): void
    {
        $actual = format_size($bytes);

        self::assertSame($expected, $actual);
    }

    #[DataProvider('memoryProvider')]
    public function test_it_can_convert_a_memory_limit_amount_to_bytes(string $memory, float|int $expected): void
    {
        $actual = memory_to_bytes($memory);

        self::assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_unique_id(): void
    {
        self::assertMatchesRegularExpression('/^(?:[a-z]|\d){12}$/', unique_id(''));
        self::assertMatchesRegularExpression('/^Box(?:[a-z]|\d){12}$/', unique_id('Box'));
    }

    public static function pharCompressionAlgorithmProvider(): iterable
    {
        yield [Phar::GZ, 'zlib'];
        yield [Phar::BZ2, 'bz2'];
        yield [Phar::NONE, null];
        yield [10, -1];
    }

    public static function bytesProvider(): iterable
    {
        yield [10, '10.00B'];
        yield [1024, '1.00KB'];
        yield [1024 ** 2, '1.00MB'];
        yield [1024 ** 3, '1.00GB'];
        yield [1024 ** 4, '1.00TB'];
        yield [1024 ** 5, '1.00PB'];
        yield [1024 ** 6, '1.00EB'];
        yield [1.073741824E+21, '931.32EB'];
    }

    public static function memoryProvider(): iterable
    {
        yield ['-1', -1];
        yield ['10', 10];
        yield ['1k', 1024];
        yield ['10k', 10240];
        yield ['1m', 1024 ** 2];
        yield ['10m', (1024 ** 2) * 10];
        yield ['1g', 1024 ** 3];
        yield ['10g', (1024 ** 3) * 10];
        yield ['10g', (1024 ** 3) * 10];
        yield ['1000000000000g', 1.073741824E+21];
    }
}
