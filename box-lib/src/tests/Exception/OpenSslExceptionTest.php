<?php

namespace Herrera\Box\Tests\Exception;

use Herrera\Box\Exception\OpenSslException;
use Herrera\PHPUnit\TestCase;

class OpenSslExceptionTest extends TestCase
{
    public function testLastError()
    {
        if (false === extension_loaded('openssl')) {
            $this->markTestSkipped(
                'The "openssl" extension is required to test the exception.'
            );
        }

        OpenSslException::reset();

        openssl_pkey_get_private('test', 'test');

        $exception = OpenSslException::lastError();

        $this->assertRegExp('/PEM routines/', $exception->getMessage());
    }

    public function testReset()
    {
        openssl_pkey_get_private('test', 'test');

        OpenSslException::reset();

        $this->assertEmpty(openssl_error_string());
    }

    public function testResetWarning()
    {
        openssl_pkey_get_private('test' . rand(), 'test' . rand());

        restore_error_handler();

        @OpenSslException::reset(0);

        $error = error_get_last();

        $this->assertEquals(
            'The OpenSSL error clearing loop has exceeded 0 rounds.',
            $error['message']
        );
    }

    protected function setUp()
    {
        if (false === extension_loaded('openssl')) {
            $this->markTestSkipped('The "openssl" extension is not available.');
        }
    }
}
