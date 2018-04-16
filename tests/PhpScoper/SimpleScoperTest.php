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
use KevinGH\Box\Compactor\PhpScoper;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

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
        $whitelist = ['Whitelisted\Foo'];
        $patchers = [];

        /** @var ObjectProphecy|PhpScoper $phpScoperProphecy */
        $phpScoperProphecy = $this->prophesize(Scoper::class);
        $phpScoperProphecy
            ->scope($file, $contents, $prefix, $patchers, $whitelist)
            ->willReturn(
                $expected = 'Scoped file'
            )
        ;
        /** @var PhpScoper $phpScoper */
        $phpScoper = $phpScoperProphecy->reveal();

        $scoper = new SimpleScoper($phpScoper, $prefix, $whitelist, $patchers);

        $actual = $scoper->scope($file, $contents);

        $this->assertSame($expected, $actual);

        $phpScoperProphecy->scope(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_exposes_some_elements_of_the_scoping_config(): void
    {
        $prefix = 'HumbugBox';
        $whitelist = ['Whitelisted\Foo'];
        $patchers = [];

        $scoper = new SimpleScoper(new FakePhpScoper(), $prefix, $whitelist, $patchers);

        $this->assertSame($prefix, $scoper->getPrefix());
        $this->assertSame($whitelist, $scoper->getWhitelist());
    }
}
