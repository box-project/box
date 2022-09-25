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

namespace KevinGH\Box\Json;

use Error;
use InvalidArgumentException;
use function KevinGH\Box\FileSystem\dump_file;
use KevinGH\Box\Test\FileSystemTestCase;

/**
 * @covers \KevinGH\Box\Json\JsonValidationException
 */
class JsonValidationExceptionTest extends FileSystemTestCase
{
    public function test_it_cannot_be_created_with_a_non_existent_file(): void
    {
        try {
            new JsonValidationException('message', 'unknown file');

            $this->fail('Expected exception to be thrown');
        } catch (InvalidArgumentException $exception) {
            static::assertSame('The file "unknown file" does not exist.', $exception->getMessage());
        }
    }

    public function test_it_cannot_be_created_with_a_non_valid_errors(): void
    {
        try {
            new JsonValidationException('message', null, [false]);

            $this->fail('Expected exception to be thrown');
        } catch (InvalidArgumentException $exception) {
            static::assertSame('Expected a string. Got: boolean', $exception->getMessage());
        }
    }

    public function test_it_cannot_be_instantiated(): void
    {
        $message = 'my message';

        $exception = new JsonValidationException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getValidatedFile());
        $this->assertSame([], $exception->getErrors());

        dump_file($file = 'dummy_file', '');
        $errors = ['foo', 'bar'];
        $code = 10;
        $error = new Error();

        $exception = new JsonValidationException($message, $file, $errors, $code, $error);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($error, $exception->getPrevious());
        $this->assertSame($file, $exception->getValidatedFile());
        $this->assertSame($errors, $exception->getErrors());
    }
}
