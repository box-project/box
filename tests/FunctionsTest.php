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
    public function test_it_can_provide_the_PHARs_algorithms(): void
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
     * @dataProvider providePharCompressionAlgorithm
     *
     * @param mixed $expected
     */
    public function test_it_can_provide_the_PHARs_algorithm_extensions(int $algorithm, $expected): void
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
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider provideBytes
     */
    public function test_it_can_format_bytes(int $bytes, string $expected): void
    {
        $actual = format_size($bytes);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideMemory
     */
    public function test_it_can_convet_a_memory_limit_amount_to_bytes(string $memory, int $expected): void
    {
        $actual = memory_to_bytes($memory);

        $this->assertSame($expected, $actual);
    }

    public function providePharCompressionAlgorithm()
    {
        yield [Phar::GZ, 'zlib'];
        yield [Phar::BZ2, 'bz2'];
        yield [Phar::NONE, null];
        yield [10, -1];
    }

    public function provideBytes()
    {
        yield [10, '10.00B'];
        yield [1024, '1.00KB'];
        yield [1024 ** 2, '1.00MB'];
        yield [1024 ** 3, '1.00GB'];
        yield [1024 ** 4, '1.00TB'];
        yield [1024 ** 5, '1.00PB'];
        yield [1024 ** 6, '1.00EB'];
    }

    public function provideMemory()
    {
        yield ['-1', -1];
        yield ['10', 10];
        yield ['1k', 1024];
        yield ['10k', 10240];
        yield ['1m', 1024 ** 2];
        yield ['10m', (1024 ** 2) * 10];
        yield ['1g', 1024 ** 3];
        yield ['10g', (1024 ** 3) * 10];
    }
}
