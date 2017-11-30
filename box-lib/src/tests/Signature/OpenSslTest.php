<?php

namespace Herrera\Box\Tests\Signature;

use Herrera\Box\Exception\OpenSslException;
use Herrera\Box\Signature\OpenSsl;
use Herrera\PHPUnit\TestCase;
use PHPUnit\Framework\Error\Warning;
use Throwable;

class OpenSslTest extends TestCase
{
    /**
     * @var OpenSsl
     */
    private $hash;

    public function testVerify()
    {
        $path = RES_DIR . '/openssl.phar';

        $this->hash->init('openssl', $path);
        $this->hash->update(
            file_get_contents($path, null, null, 0, filesize($path) - 76)
        );

        $this->assertTrue(
            $this->hash->verify(
                '54AF1D4E5459D3A77B692E46FDB9C965D1C7579BD1F2AD2BECF4973677575444FE21E104B7655BA3D088090C28DF63D14876B277C423C8BFBCDB9E3E63F9D61A'
            )
        );
    }

    public function testVerifyErrorHandlingBug()
    {
        $dir = $this->createDir();
        $path = "$dir/openssl.phar";

        copy(RES_DIR . '/openssl.phar', $path);
        touch("$path.pubkey");

        $this->hash->init('openssl', $path);
        $this->hash->update(
            file_get_contents($path, null, null, 0, filesize($path) - 76)
        );

        try {
            $this->hash->verify('it dont matter, aight');

            $this->fail('Expected exception to be thrown.');
        } catch (OpenSslException $exception) {
            //TODO: check why this is not called instead
            $this->assertRegExp(
                '/cannot be coerced/',
                $exception->getMessage()
            );
        } catch (Throwable $throwable) {
            $this->assertRegExp(
                '/cannot be coerced/',
                $throwable->getMessage()
            );
        }
    }

    protected function setUp()
    {
        $this->hash = new OpenSsl();
    }
}
