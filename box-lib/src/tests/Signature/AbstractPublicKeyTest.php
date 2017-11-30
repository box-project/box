<?php

namespace Herrera\Box\Tests\Signature;

use Herrera\PHPUnit\TestCase;

class AbstractPublicKeyTest extends TestCase
{
    /**
     * @var PublicKey
     */
    private $hash;

    public function testInit()
    {
        unlink($file = $this->createFile());

        file_put_contents($file . '.pubkey', 'abc');

        $this->hash->init('abc', $file);

        $this->assertEquals(
            'abc',
            $this->getPropertyValue($this->hash, 'key')
        );
    }

    /**
     * @expectedException \Herrera\Box\Exception\FileException
     * @expectedExceptionMessage No such file or directory
     */
    public function testInitNotExist()
    {
        $this->hash->init('abc', '/does/not/exist');
    }

    public function testGetKey()
    {
        $this->setPropertyValue($this->hash, 'key', 'abc');

        $this->assertEquals('abc', $this->callMethod($this->hash, 'getKey'));
    }

    protected function setUp()
    {
        $this->hash = new PublicKey();
    }
}
