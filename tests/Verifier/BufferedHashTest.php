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

use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Verifier\BufferedHash
 */
class BufferedHashTest extends TestCase
{
    public function test_buffers_data_on_update(): void
    {
        $hash = new DummyBufferedHash('', '');

        $hash->update('Hello');
        $hash->update(' ');
        $hash->update('world!');

        $expected = 'Hello world!';
        $actual = $hash->getPublicBufferedData();

        $this->assertSame($expected, $actual);
    }
}
