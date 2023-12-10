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
use Fidry\FileSystem\FS;
use InvalidArgumentException;
use KevinGH\Box\Test\FileSystemTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(JsonValidationException::class)]
class JsonValidationExceptionTest extends FileSystemTestCase
{
    public function test_it_cannot_be_created_with_a_non_existent_file(): void
    {
        try {
            new JsonValidationException('message', 'unknown file');

            self::fail('Expected exception to be thrown');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('The file "unknown file" does not exist.', $exception->getMessage());
        }
    }

    public function test_it_cannot_be_created_with_a_non_valid_errors(): void
    {
        try {
            new JsonValidationException('message', null, [false]);

            self::fail('Expected exception to be thrown');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Expected a string. Got: boolean', $exception->getMessage());
        }
    }

    public function test_it_cannot_be_instantiated(): void
    {
        $message = 'my message';

        $exception = new JsonValidationException($message);

        self::assertSame($message, $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertNull($exception->getValidatedFile());
        self::assertSame([], $exception->getErrors());

        FS::dumpFile($file = 'dummy_file');
        $errors = ['foo', 'bar'];
        $code = 10;
        $error = new Error();

        $exception = new JsonValidationException($message, $file, $errors, $code, $error);

        self::assertSame($message, $exception->getMessage());
        self::assertSame($code, $exception->getCode());
        self::assertSame($error, $exception->getPrevious());
        self::assertSame($file, $exception->getValidatedFile());
        self::assertSame($errors, $exception->getErrors());
    }
}
