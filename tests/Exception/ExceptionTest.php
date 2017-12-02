<?php

namespace KevinGH\Box\Exception;

use KevinGH\Box\Exception\Exception;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testCreate()
    {
        $exception = Exception::create('My %s.', 'message');

        $this->assertEquals('My message.', $exception->getMessage());
    }

    public function testLastError()
    {
        /** @noinspection PhpExpressionResultUnusedInspection */
        /** @noinspection PhpUndefinedVariableInspection */
        @$test;

        $exception = Exception::lastError();

        $this->assertEquals('Undefined variable: test', $exception->getMessage());
    }
}
