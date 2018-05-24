<?php

declare(strict_types=1);

namespace KevinGH\Box\Json;


use Error;
use InvalidArgumentException;
use function KevinGH\Box\FileSystem\dump_file;
use KevinGH\Box\Test\FileSystemTestCase;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Json\JsonValidationException
 */
class JsonValidationExceptionTest extends FileSystemTestCase
{
    public function test_it_cannot_be_created_with_a_non_existent_file()
    {
        try {
            new JsonValidationException('message', 'unknown file');

            $this->fail('Expected exception to be thrown');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "unknown file" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_be_created_with_a_non_valid_errors()
    {
        try {
            new JsonValidationException('message', null, [false]);

            $this->fail('Expected exception to be thrown');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Value "<FALSE>" expected to be string, type boolean given.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_be_instantiated()
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
