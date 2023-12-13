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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NoConfigurationFound::class)]
class NoConfigurationFoundTest extends TestCase
{
    public function test_it_can_be_created_with_a_default_error_message(): void
    {
        $exception = new NoConfigurationFound();

        self::assertSame(
            'The configuration file could not be found.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function test_it_can_be_created_with_overridden_values(): void
    {
        $message = 'My message';
        $code = 120;
        $error = new Error();

        $exception = new NoConfigurationFound($message, $code, $error);

        self::assertSame($message, $exception->getMessage());
        self::assertSame($code, $exception->getCode());
        self::assertSame($error, $exception->getPrevious());
    }
}
