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

use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Scoper\NullScoper;
use Humbug\PhpScoper\Scoper\PhpScoper;
use Humbug\PhpScoper\Whitelist;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use function serialize;
use function unserialize;

/**
 * @covers \KevinGH\Box\PhpScoper\SerializablePhpScoper
 */
class SerializablePhpScoperTest extends TestCase
{
    /** @var PhpScoper&ObjectProphecy */
    private $decoratedScoperProphecy;

    /** @var PhpScoper */
    private $decoratedScoper;

    /** @var SerializablePhpScoper */
    private $serializableScoper;

    protected function setUp(): void
    {
        $this->decoratedScoperProphecy = $this->prophesize(Scoper::class);
        $this->decoratedScoper = $this->decoratedScoperProphecy->reveal();

        $this->serializableScoper = new SerializablePhpScoper(function () {
            return $this->decoratedScoper;
        });
    }

    public function test_it_exposes_the_decorated_scoper(): void
    {
        $this->assertSame($this->decoratedScoper, $this->serializableScoper->getScoper());
    }

    public function test_it_uses_the_decorated_scoper_to_scope_a_file(): void
    {
        $file = 'file';
        $contents = 'something';
        $prefix = 'HumbugBox';
        $whitelist = Whitelist::create(true, true, true, 'Whitelisted\Foo');
        $patchers = [];

        $this->decoratedScoperProphecy
            ->scope($file, $contents, $prefix, $patchers, $whitelist)
            ->willReturn($expected = 'foo')
        ;

        $actual = $this->serializableScoper->scope($file, $contents, $prefix, $patchers, $whitelist);

        $this->assertSame($expected, $actual);

        $this->decoratedScoperProphecy->scope(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_is_serializable(): void
    {
        // Uses a new compactor instance for which the closure is not linked to this test
        $compactor = new SerializablePhpScoper(static function () {
            return new NullScoper();
        });

        $decoratedCompactor = $compactor->getScoper();

        /** @var SerializablePhpScoper $deserializedCompactor */
        $deserializedCompactor = unserialize(serialize($compactor));

        $this->assertInstanceOf(SerializablePhpScoper::class, $deserializedCompactor);
        $this->assertNotSame($compactor, $deserializedCompactor);
        $this->assertInstanceOf(NullScoper::class, $deserializedCompactor->getScoper());
        $this->assertNotSame($decoratedCompactor, $deserializedCompactor->getScoper());
    }

    public function test_it_is_serializable_multiple_times(): void
    {
        $compactor = unserialize(serialize(new SerializablePhpScoper(static function () {
            return new NullScoper();
        })));

        $decoratedCompactor = $compactor->getScoper();

        /** @var SerializablePhpScoper $deserializedCompactor */
        $deserializedCompactor = unserialize(serialize($compactor));

        $this->assertInstanceOf(SerializablePhpScoper::class, $deserializedCompactor);
        $this->assertNotSame($compactor, $deserializedCompactor);
        $this->assertInstanceOf(NullScoper::class, $deserializedCompactor->getScoper());
        $this->assertNotSame($decoratedCompactor, $deserializedCompactor->getScoper());
    }
}
