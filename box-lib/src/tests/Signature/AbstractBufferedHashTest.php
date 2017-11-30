<?php

namespace Herrera\Box\Tests\Signature;

use Herrera\PHPUnit\TestCase;

class AbstractBufferedHashTest extends TestCase
{
    /**
     * @var BufferedHash
     */
    private $hash;

    public function testUpdate()
    {
        $this->hash->update('a');
        $this->hash->update('b');
        $this->hash->update('c');

        $this->assertEquals(
            'abc',
            $this->getPropertyValue($this->hash, 'data')
        );
    }

    public function testGetData()
    {
        $this->setPropertyValue($this->hash, 'data', 'abc');

        $this->assertEquals('abc', $this->callMethod($this->hash, 'getData'));
    }

    protected function setUp()
    {
        $this->hash = new BufferedHash();
    }
}
