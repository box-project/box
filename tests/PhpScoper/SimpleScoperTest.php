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
use Humbug\PhpScoper\Whitelist;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use function serialize;
use function unserialize;

/**
 * @covers \KevinGH\Box\PhpScoper\SimpleScoper
 */
class SimpleScoperTest extends TestCase
{
    public function test_it_scopes_the_file_content(): void
    {
        $file = 'foo';
        $contents = <<<'JSON'
{
    "foo": "bar"
    
}
JSON;

        $prefix = 'HumbugBox';
        $whitelist = Whitelist::create(true, true, true, 'Whitelisted\Foo');
        $patchers = [];

        /** @var ObjectProphecy&Scoper $phpScoperProphecy */
        $phpScoperProphecy = $this->prophesize(Scoper::class);
        $phpScoperProphecy
            ->scope($file, $contents, $prefix, $patchers, $whitelist)
            ->willReturn(
                $expected = 'Scoped file'
            )
        ;
        /** @var Scoper $phpScoper */
        $phpScoper = $phpScoperProphecy->reveal();

        $actual = (new SimpleScoper($phpScoper, $prefix, $whitelist, $patchers))->scope($file, $contents);

        $this->assertSame($expected, $actual);

        $phpScoperProphecy->scope(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_exposes_some_elements_of_the_scoping_config(): void
    {
        $prefix = 'HumbugBox';
        $whitelist = Whitelist::create(true, true, true, 'Whitelisted\Foo');
        $patchers = [];

        $scoper = new SimpleScoper(new FakePhpScoper(), $prefix, $whitelist, $patchers);

        $this->assertSame($prefix, $scoper->getPrefix());
        $this->assertSame($whitelist, $scoper->getWhitelist());
    }

    public function test_it_can_change_of_whitelist(): void
    {
        $prefix = 'HumbugBox';
        $whitelist = Whitelist::create(true, true, true, 'Whitelisted\Foo');
        $patchers = [];

        $newWhitelist = Whitelist::create(false, false, false);

        $scoper = new SimpleScoper(new FakePhpScoper(), $prefix, $whitelist, $patchers);
        $scoper->changeWhitelist($newWhitelist);

        $this->assertSame($prefix, $scoper->getPrefix());
        $this->assertSame($newWhitelist, $scoper->getWhitelist());
    }

    public function test_it_is_serializable(): void
    {
        $scoper = new SimpleScoper(
            new FakePhpScoper(),
            'HumbugBox',
            Whitelist::create(true, true, true, 'Whitelisted\Foo'),
            []
        );

        $this->assertEquals(
            $scoper,
            unserialize(serialize($scoper))
        );
    }
}
