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

namespace KevinGH\Box\Configuration;

use Error;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Configuration\NoConfigurationFound
 */
class NoConfigurationFoundTest extends TestCase
{
    public function test_it_can_be_created_with_a_default_error_message(): void
    {
        $exception = new NoConfigurationFound();

        $this->assertSame(
            'The configuration file could not be found.',
            $exception->getMessage(),
        );
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function test_it_can_be_created_with_overridden_values(): void
    {
        $message = 'My message';
        $code = 120;
        $error = new Error();

        $exception = new NoConfigurationFound($message, $code, $error);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($error, $exception->getPrevious());
    }
}
