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

use InvalidArgumentException;
use Phar;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class FunctionsTest extends TestCase
{
    public function test_it_can_provide_the_phars_algorithms(): void
    {
        $expected = [
            'GZ' => Phar::GZ,
            'BZ2' => Phar::BZ2,
            'NONE' => Phar::NONE,
        ];

        $actual = get_phar_compression_algorithms();

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider pharCompressionAlgorithmProvider
     */
    public function test_it_can_provide_the_phars_algorithm_extensions(int $algorithm, mixed $expected): void
    {
        try {
            $actual = get_phar_compression_algorithm_extension($algorithm);

            if (-1 === $expected) {
                $this->fail('Expected exception to be thrown.');
            }

            $this->assertSame($expected, $actual);
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Unknown compression algorithm code "'.$algorithm.'"',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @dataProvider bytesProvider
     */
    public function test_it_can_format_bytes(float|int $bytes, string $expected): void
    {
        $actual = format_size($bytes);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider memoryProvider
     */
    public function test_it_can_convert_a_memory_limit_amount_to_bytes(string $memory, float|int $expected): void
    {
        $actual = memory_to_bytes($memory);

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_generate_a_unique_id(): void
    {
        $this->assertMatchesRegularExpression('/^(?:[a-z]|\d){12}$/', unique_id(''));
        $this->assertMatchesRegularExpression('/^Box(?:[a-z]|\d){12}$/', unique_id('Box'));
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
