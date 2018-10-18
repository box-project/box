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

namespace KevinGH\Box\Verifier;

use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use function md5;
use function strtoupper;

/**
 * @covers \KevinGH\Box\Verifier\Hash
 */
class HashTest extends TestCase
{
    public function test_it_cannot_verify_data_with_an_unknown_algorithm(): void
    {
        try {
            new Hash('bad algorithm', '');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertRegExp(
                '/^Expected badalgorithm to be a known algorithm\:.+$/',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider provideData
     */
    public function test_it_can_verify_data(string $algorithm, string $data, string $hashedData, bool $expected): void
    {
        $hash = new Hash($algorithm, '');

        $hash->update($data);

        $actual = $hash->verify($hashedData);

        $this->assertSame($expected, $actual);
    }

    public function provideData(): Generator
    {
        yield 'md5' => [
            'md5',
            'unhashed data',
            strtoupper(md5('unhashed data')),
            true,
        ];

        yield 'md5 with different case' => [
            'MD5',
            'unhashed data',
            strtoupper(md5('unhashed data')),
            true,
        ];

        yield 'invalid md5' => [
            'md5',
            'unhashed data',
            strtoupper(md5('different data')),
            false,
        ];
    }
}
