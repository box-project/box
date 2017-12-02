<?php

namespace KevinGH\Box\Signature;

use KevinGH\Box\Signature\Hash;
use PHPUnit\Framework\TestCase;

class HashTest extends TestCase
{
    /**
     * @var Hash
     */
    private $hash;

    /**
     * @expectedException \KevinGH\Box\Exception\Exception
     * @expectedExceptionMessage Unknown hashing algorithm
     */
    public function testInitBadAlgorithm()
    {
        $this->hash->init('bad algorithm', '');
    }

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
