<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\Box\Exception;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class OpenSslExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        if (false === extension_loaded('openssl')) {
            $this->markTestSkipped('The "openssl" extension is not available.');
        }
    }

    public function testLastError(): void
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

    public function testReset(): void
    {
        openssl_pkey_get_private('test', 'test');

        OpenSslException::reset();

        $this->assertEmpty(openssl_error_string());
    }

    public function testResetWarning(): void
    {
        openssl_pkey_get_private('test'.random_int(0, getrandmax()), 'test'.random_int(0, getrandmax()));

        restore_error_handler();

        @OpenSslException::reset(0);

        $error = error_get_last();

        $this->assertSame(
            'The OpenSSL error clearing loop has exceeded 0 rounds.',
            $error['message']
        );
    }
}
