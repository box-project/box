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

namespace KevinGH\Box\PhpScoper;

use Closure;
use function current;
use Exception;
use Generator;
use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Scoper\NullScoper;
use Humbug\PhpScoper\Whitelist;
use const PHP_VERSION_ID;
use PHPUnit\Framework\TestCase;
use function serialize;
use function unserialize;

/**
 * @covers \KevinGH\Box\PhpScoper\SerializablePhpScoper
 */
class SerializablePhpScoperTest extends TestCase
{
    public function test_it_exposes_the_decorated_scoper(): void
    {
        $decoratedScoper = new DummyScoper();
        $scoper = new SerializablePhpScoper(static function () use ($decoratedScoper) {
            return $decoratedScoper;
        });
        $this->assertSame($decoratedScoper, $scoper->getScoper());
    }

    public function test_it_uses_the_decorated_scoper_to_scope_a_file(): void
    {
        $arguments = [
            'file',
            $contents = 'something',
            'HumbugBox',
            [],
            Whitelist::create(true, true, true, 'Whitelisted\Foo'),
        ];

        $decoratedScoper = new CallRecorderScoper();

        $scoper = new SerializablePhpScoper(static function () use ($decoratedScoper): Scoper {
            return $decoratedScoper;
        });

        $actual = $scoper->scope(...$arguments);

        $this->assertSame($contents, $actual);

        $this->assertCount(1, $decoratedScoper->getCalls());
        $this->assertSame($arguments, current($decoratedScoper->getCalls()));
    }

    public function test_it_is_serializable(): void
    {
        $scoper = new SerializablePhpScoper(static function () {
            return new NullScoper();
        });

        $decoratedScoper = $scoper->getScoper();

        /** @var SerializablePhpScoper $deserializedCompactor */
        $deserializedCompactor = unserialize(serialize($scoper));

        $this->assertInstanceOf(SerializablePhpScoper::class, $deserializedCompactor);
        $this->assertNotSame($scoper, $deserializedCompactor);
        $this->assertInstanceOf(NullScoper::class, $deserializedCompactor->getScoper());
        $this->assertNotSame($decoratedScoper, $deserializedCompactor->getScoper());
    }

    public function test_it_is_serializable_multiple_times(): void
    {
        $scoper = unserialize(serialize(new SerializablePhpScoper(static function () {
            return new NullScoper();
        })));

        $decoratedScoper = $scoper->getScoper();

        /** @var SerializablePhpScoper $deserializedScoper */
        $deserializedScoper = unserialize(serialize($scoper));

        $this->assertInstanceOf(SerializablePhpScoper::class, $deserializedScoper);
        $this->assertNotSame($scoper, $deserializedScoper);
        $this->assertInstanceOf(NullScoper::class, $deserializedScoper->getScoper());
        $this->assertNotSame($decoratedScoper, $deserializedScoper->getScoper());
    }

    /**
     * @dataProvider provideScoperFactories
     */
    public function test_it_check_if_the_closure_given_is_serializable_upfront(
        Closure $createScoper,
        ?string $expectedError
    ): void {
        try {
            $scoper = new SerializablePhpScoper($createScoper);

            if (null !== $expectedError) {
                $this->fail('Expected exception to be thrown.');
            }
        } catch (Exception $exception) {
            if (null === $expectedError) {
                $this->fail('Did not expect an exception to be thrown.');
            }

            $this->assertSame(
                $expectedError,
                $exception->getMessage()
            );

            return;
        }

        // Manually serialize the scoper to ensure the check has been done at the instantiation time and not lazily
        /** @var SerializablePhpScoper $deserializedScoper */
        $deserializedScoper = unserialize(serialize($scoper));

        $this->assertInstanceOf(SerializablePhpScoper::class, $deserializedScoper);
    }

    public function provideScoperFactories(): Generator
    {
        yield 'unserializable class' => [
            static function () {
                return new UnserializableScoper();
            },
            null,
        ];

        yield 'anonymous class' => [
            static function () {
                return new class() implements Scoper {
                    /**
                     * {@inheritdoc}
                     */
                    public function scope(string $filePath, string $contents, string $prefix, array $patchers, Whitelist $whitelist): string
                    {
                        return $contents;
                    }
                };
            },
            null,
        ];

        yield 'unserializable class as binding' => (static function (): array {
            $scoper = new UnserializableScoper();

            return [
                static function () use ($scoper) {
                    return $scoper;
                },
                'This class is not serializable',
            ];
        })();

        yield 'anonymous class as binding' => (static function (): array {
            $scoper = new class() implements Scoper {
                /**
                 * {@inheritdoc}
                 */
                public function scope(string $filePath, string $contents, string $prefix, array $patchers, Whitelist $whitelist): string
                {
                    return $contents;
                }
            };

            return [
                static function () use ($scoper) {
                    return $scoper;
                },
                PHP_VERSION_ID < 80000
                    ? "Serialization of 'class@anonymous' is not allowed"
                    : "Serialization of 'Humbug\PhpScoper\Scoper@anonymous' is not allowed",
            ];
        })();
    }
}
