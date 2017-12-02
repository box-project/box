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
class ExceptionTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = Exception::create('My %s.', 'message');

        $this->assertSame('My message.', $exception->getMessage());
    }

    public function testLastError(): void
    {
        // @noinspection PhpExpressionResultUnusedInspection
        // @noinspection PhpUndefinedVariableInspection
        @$test;

        $exception = Exception::lastError();

        $this->assertSame('Undefined variable: test', $exception->getMessage());
    }
}
