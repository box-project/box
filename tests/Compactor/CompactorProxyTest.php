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

namespace KevinGH\Box\Compactor;

use Generator;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\CompactorProxy;
use KevinGH\Box\Compactor\FakeCompactor;
use KevinGH\Box\Compactor\Json;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use function serialize;
use function unserialize;

/**
 * @covers \KevinGH\Box\Compactor\CompactorProxy
 */
class CompactorProxyTest extends TestCase
{
    /** @var Compactor&ObjectProphecy */
    private $decoratedCompactorProphecy;

    /** @var Compactor */
    private $decoratedCompactor;

    /** @var CompactorProxy */
    private $compactor;

    protected function setUp(): void
    {
        $this->decoratedCompactorProphecy = $this->prophesize(Compactor::class);
        $this->decoratedCompactor = $this->decoratedCompactorProphecy->reveal();

        $this->compactor = new CompactorProxy(function () {
            return $this->decoratedCompactor;
        });
    }

    public function test_it_exposes_the_decorated_compactor(): void
    {
        $this->assertSame($this->decoratedCompactor, $this->compactor->getCompactor());
    }

    public function test_it_uses_the_decorated_compactor_to_compact_a_file(): void
    {
        $file = 'file';
        $contents = 'something';

        $this->decoratedCompactorProphecy->compact($file, $contents)->willReturn($expected = 'foo');

        $actual = $this->compactor->compact($file, $contents);

        $this->assertSame($expected, $actual);

        $this->decoratedCompactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_is_serializable(): void
    {
        // Uses a new compactor instance for which the closure is not linked to this test
        $compactor = new CompactorProxy(static function (): Compactor {
            return new FakeCompactor();
        });

        $decoratedCompactor = $compactor->getCompactor();

        /** @var CompactorProxy $deserializedCompactor */
        $deserializedCompactor = unserialize(serialize($compactor));

        $this->assertInstanceOf(CompactorProxy::class, $deserializedCompactor);
        $this->assertNotSame($compactor, $deserializedCompactor);
        $this->assertInstanceOf(FakeCompactor::class, $deserializedCompactor->getCompactor());
        $this->assertNotSame($decoratedCompactor, $deserializedCompactor->getCompactor());
    }

    public function test_it_is_serializable_multiple_times(): void
    {
        $compactor = unserialize(serialize(new CompactorProxy(static function (): Compactor {
            return new FakeCompactor();
        })));

        $decoratedCompactor = $compactor->getCompactor();

        /** @var CompactorProxy $deserializedCompactor */
        $deserializedCompactor = unserialize(serialize($compactor));

        $this->assertInstanceOf(CompactorProxy::class, $deserializedCompactor);
        $this->assertNotSame($compactor, $deserializedCompactor);
        $this->assertInstanceOf(FakeCompactor::class, $deserializedCompactor->getCompactor());
        $this->assertNotSame($decoratedCompactor, $deserializedCompactor->getCompactor());
    }
}
