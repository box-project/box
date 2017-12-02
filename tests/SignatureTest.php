<?php

namespace KevinGH\Box;

use KevinGH\Box\Exception\FileException;
use KevinGH\Box\Signature;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use Phar;
use PharException;
use PHPUnit\Framework\TestCase;
use TypeError;

class SignatureTest extends TestCase
{
    public const FIXTURES_DIR = __DIR__.'/../fixtures/signature';
    
    private $types;

    public function getPhars()
    {
        return array(
            array(self::FIXTURES_DIR . '/md5.phar'),
            array(self::FIXTURES_DIR . '/sha1.phar'),
            array(self::FIXTURES_DIR . '/sha256.phar'),
            array(self::FIXTURES_DIR . '/sha512.phar'),
            array(self::FIXTURES_DIR . '/openssl.phar'),
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
            Signature::class,
            Signature::create(self::FIXTURES_DIR . '/example.phar')
        );
    }

    public function testCreateNoGbmb()
    {
        $path = realpath(self::FIXTURES_DIR . '/missing.phar');
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
        $path = realpath(self::FIXTURES_DIR . '/invalid.phar');
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
        $path = realpath(self::FIXTURES_DIR . '/missing.phar');
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
}
