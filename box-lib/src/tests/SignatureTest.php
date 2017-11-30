<?php

namespace Herrera\Box\Tests;

use Herrera\Box\Exception\FileException;
use Herrera\Box\Signature;
use Herrera\PHPUnit\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use Phar;
use PharException;

class SignatureTest extends TestCase
{
    private $types;

    public function getPhars()
    {
        return array(
            array(RES_DIR . '/md5.phar'),
            array(RES_DIR . '/sha1.phar'),
            array(RES_DIR . '/sha256.phar'),
            array(RES_DIR . '/sha512.phar'),
            array(RES_DIR . '/openssl.phar'),
        );
    }

    public function testConstruct()
    {
        $path = RES_DIR . '/example.phar';

        $sig = new Signature($path);

        $this->assertEquals(
            realpath($path),
            $this->getPropertyValue($sig, 'file')
        );

        $this->assertSame(
            filesize($path),
            $this->getPropertyValue($sig, 'size')
        );
    }

    public function testConstructNotExist()
    {
        try {
            new Signature('/does/not/exist');

            $this->fail('Expected exception to be thrown.');
        } catch (FileException $exception) {
            $this->assertSame(
                'The path "/does/not/exist" does not exist or is not a file.',
                $exception->getMessage()
            );
        }
    }

    public function testCreate()
    {
        $this->assertInstanceOf(
            'Herrera\\Box\\Signature',
            Signature::create(RES_DIR . '/example.phar')
        );
    }

    public function testCreateNoGbmb()
    {
        $path = realpath(RES_DIR . '/missing.phar');
        $sig = new Signature($path);

        try {
            $sig->get();

            $this->fail('Expected exception to be thrown.');
        } catch (PharException $exception) {
            $this->assertSame(
                "The phar \"$path\" is not signed.",
                $exception->getMessage()
            );
        }
    }

    public function testCreateInvalid()
    {
        $path = realpath(RES_DIR . '/invalid.phar');
        $sig = new Signature($path);

        try {
            $sig->get(true);

            $this->fail('Expected exception to be thrown.');
        } catch (PharException $exception) {
            $this->assertSame(
                "The signature type (ffffffff) is not recognized for the phar \"$path\".",
                $exception->getMessage()
            );
        }
    }

    public function testCreateMissingNoRequire()
    {
        $path = realpath(RES_DIR . '/missing.phar');
        $sig = new Signature($path);

        $this->assertNull($sig->get(false));
    }

    /**
     * @dataProvider getPhars
     */
    public function testGet($path)
    {
        $phar = new Phar($path);
        $sig = new Signature($path);

        $this->assertEquals(
            $phar->getSignature(),
            $sig->get()
        );
    }

    /**
     * @dataProvider getPhars
     */
    public function testVerify($path)
    {
        $sig = new Signature($path);

        $this->assertTrue($sig->verify());
    }

    public function testHandle()
    {
        $sig = new Signature(__FILE__);

        $this->setPropertyValue($sig, 'file', '/does/not/exist');

        try {
            $this->callMethod($sig, 'handle');

            $this->fail('Expected exception to be thrown.');
        } catch (FileException $exception) {
            $this->assertRegExp(
                '/No such file or directory/',
                $exception->getMessage()
            );
        }
    }

    public function testRead()
    {
        $sig = new Signature(__FILE__);

        $this->setPropertyValue($sig, 'handle', true);

        try {
            $this->callMethod($sig, 'read', array(123));

            $this->fail('Expected exception to be thrown.');
        } catch (FileException $exception) {
            $this->assertRegExp(
                '/boolean given/',
                $exception->getMessage()
            );
        }
    }

    public function testReadShort()
    {
        $file = $this->createFile();
        $sig = new Signature($file);

        try {
            $this->callMethod($sig, 'read', array(1));

            $this->fail('Expected exception to be thrown.');
        } catch (FileException $exception) {
            $this->assertSame(
                "Only read 0 of 1 bytes from \"$file\".",
                $exception->getMessage()
            );
        }
    }

    /**
     * @expectedException \Herrera\Box\Exception\FileException
     */
    public function testSeek()
    {
        $file = $this->createFile();
        $sig = new Signature($file);

        $this->callMethod($sig, 'seek', array(-1));
    }

    protected function setUp()
    {
        $this->types = $this->getPropertyValue(
            'Herrera\\Box\\Signature',
            'types'
        );
    }

    protected function tearDown()
    {
        $this->setPropertyValue(
            'Herrera\\Box\\Signature',
            'types',
            $this->types
        );

        parent::tearDown();
    }
}
