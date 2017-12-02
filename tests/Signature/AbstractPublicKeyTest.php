<?php

namespace KevinGH\Box\Signature;

use PHPUnit\Framework\TestCase;

class AbstractPublicKeyTest extends TestCase
{
    /**
     * @var PublicKey
     */
    private $hash;

    /**
     * @expectedException \KevinGH\Box\Exception\FileException
     */
    public function testInitNotExist()
    {
        $this->hash->init('abc', '/does/not/exist');
    }

    protected function setUp()
    {
        $this->hash = new PublicKey();
    }
}
