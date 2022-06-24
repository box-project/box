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

use Error;
use KevinGH\Box\PhpScoper\FakeScoper;
use KevinGH\Box\PhpScoper\Scoper;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use function serialize;
use function unserialize;

/**
 * @covers \KevinGH\Box\Compactor\PhpScoper
 */
class PhpScoperTest extends TestCase
{
    use ProphecyTrait;

    public function test_it_scopes_the_file_content(): void
    {
        $file = 'foo';
        $contents = <<<'JSON'
            {
                "foo": "bar"

            }
            JSON;

        /** @var ObjectProphecy|Scoper $scoper */
        $scoperProphecy = $this->prophesize(Scoper::class);
        $scoperProphecy->scope($file, $contents)->willReturn($expected = 'Scoped contents');
        /** @var Scoper $scoper */
        $scoper = $scoperProphecy->reveal();

        $actual = (new PhpScoper($scoper))->compact($file, $contents);

        $this->assertSame($expected, $actual);

        $scoperProphecy->scope(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_returns_the_content_unchanged_if_the_scoping_failed(): void
    {
        $file = 'foo';
        $contents = <<<'JSON'
            {
                "foo": "bar"

            }
            JSON;

        /** @var ObjectProphecy|Scoper $scoper */
        $scoperProphecy = $this->prophesize(Scoper::class);
        $scoperProphecy->scope($file, $contents)->willThrow(new Error());
        /** @var Scoper $scoper */
        $scoper = $scoperProphecy->reveal();

        $actual = (new PhpScoper($scoper))->compact($file, $contents);

        $this->assertSame($contents, $actual);
    }

    public function test_it_exposes_the_scoper(): void
    {
        $scoper = new FakeScoper();

        $compactor = new PhpScoper($scoper);

        $this->assertSame($scoper, $compactor->getScoper());
    }

    public function test_it_is_serializable(): void
    {
        $compactor = new PhpScoper(new FakeScoper());

        $this->assertEquals(
            $compactor,
            unserialize(serialize($compactor)),
        );
    }
}
