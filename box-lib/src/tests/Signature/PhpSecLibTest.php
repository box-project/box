<?php

namespace Herrera\Box\Tests\Signature;

use Herrera\Box\Signature\PhpSecLib;
use Herrera\PHPUnit\TestCase;

class PhpSecLibTest extends TestCase
{
    /**
     * @var PhpSecLib
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

    protected function setUp()
    {
        $this->hash = new PhpSecLib();
    }
}
