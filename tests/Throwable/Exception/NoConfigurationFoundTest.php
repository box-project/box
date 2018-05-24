<?php

declare(strict_types=1);

namespace KevinGH\Box\Throwable\Exception;

use Error;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Throwable\Exception\NoConfigurationFound
 */
class NoConfigurationFoundTest extends TestCase
{
    public function test_it_can_be_created_with_a_default_error_message()
    {
        $exception = new NoConfigurationFound();

        $this->assertSame(
            'The configuration file could not be found.',
            $exception->getMessage()
        );
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function test_it_can_be_created_with_overridden_values()
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
