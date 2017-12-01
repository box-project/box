<?php

namespace KevinGH\Box\Signature;

use KevinGH\Box\Signature\Hash;
use Herrera\PHPUnit\TestCase;

class HashTest extends TestCase
{
    /**
     * @var Hash
     */
    private $hash;

    public function testInit()
    {
        $this->hash->init('md5', '');

        $this->assertInternalType(
            'resource',
            $this->getPropertyValue($this->hash, 'context')
        );
    }

    /**
     * @expectedException \Herrera\Box\Exception\Exception
     * @expectedExceptionMessage Unknown hashing algorithm
     */
    public function testInitBadAlgorithm()
    {
        $this->hash->init('bad algorithm', '');
    }

    /**
     * @depends testInit
     */
    public function testUpdate()
    {
        $this->hash->init('md5', '');
        $this->hash->update('test');

        $this->assertEquals(
            md5('test'),
            hash_final($this->getPropertyValue($this->hash, 'context'))
        );
    }

    /**
     * @depends testInit
     * @depends testUpdate
     */
    public function testVerify()
    {
        $this->hash->init('md5', '');
        $this->hash->update('test');

        $this->assertTrue(
            $this->hash->verify(strtoupper(md5('test')))
        );
    }

    protected function setUp()
    {
        $this->hash = new Hash();
    }
}
