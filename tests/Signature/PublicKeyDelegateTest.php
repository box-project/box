<?php

namespace KevinGH\Box\Signature;

use KevinGH\Box\Signature\PublicKeyDelegate;
use Herrera\PHPUnit\TestCase;

class PublicKeyDelegateTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/signature';

    private static $openssl = false;

    public function testConstruct()
    {
        $hash = new PublicKeyDelegate();

        if (extension_loaded('openssl')) {
            self::$openssl = true;

            $this->assertInstanceOf(
                OpenSsl::class,
                $this->getPropertyValue($hash, 'hash')
            );
        } else {
            $this->assertInstanceOf(
                PhpSecLib::class,
                $this->getPropertyValue($hash, 'hash')
            );
        }
    }

    public function testFunctional()
    {
        $path = self::FIXTURES_DIR . '/openssl.phar';
        $hash = new PublicKeyDelegate();

        $hash->init('openssl', $path);
        $hash->update(
            file_get_contents($path, null, null, 0, filesize($path) - 76)
        );

        $this->assertTrue(
            $hash->verify(
                '54AF1D4E5459D3A77B692E46FDB9C965D1C7579BD1F2AD2BECF4973677575444FE21E104B7655BA3D088090C28DF63D14876B277C423C8BFBCDB9E3E63F9D61A'
            )
        );
    }
}
