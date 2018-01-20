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
 * @covers \KevinGH\Box\Exception\OpenSslExceptionFactory
 * @requires extension openssl
 */
class OpenSslExceptionFactoryTest extends TestCase
{
    public function test_it_can_create_an_exception_for_the_last_error(): void
    {
        OpenSslExceptionFactory::reset();

        openssl_pkey_get_private('test', 'test');

        $exception = OpenSslExceptionFactory::createForLastError();

        $this->assertRegExp('/PEM routines/', $exception->getMessage());
    }

    public function test_it_can_reset_the_stack_of_errors(): void
    {
        openssl_pkey_get_private('test', 'test');

        OpenSslExceptionFactory::reset();

        $this->assertEmpty(openssl_error_string());
    }

    public function test_it_triggers_a_warning_to_prevent_broken_looping(): void
    {
        openssl_pkey_get_private('test'.random_int(0, getrandmax()), 'test'.random_int(0, getrandmax()));

        restore_error_handler();

        @OpenSslExceptionFactory::reset(0);

        $error = error_get_last();

        $this->assertSame(
            'The OpenSSL error clearing loop has exceeded 0 rounds.',
            $error['message']
        );
    }
}
