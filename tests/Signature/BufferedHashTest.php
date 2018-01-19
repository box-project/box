<?php

declare(strict_types=1);

namespace KevinGH\Box\Signature;

use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Signature\BufferedHash
 */
class BufferedHashTest extends TestCase
{
    public function test_buffers_data_on_update()
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
