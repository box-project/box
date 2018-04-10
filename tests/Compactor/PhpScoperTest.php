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

namespace KevinGH\Box;

use Error;
use Humbug\PhpScoper\Configuration;
use Humbug\PhpScoper\Scoper;
use KevinGH\Box\Compactor\PhpScoper;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @covers \KevinGH\Box\Compactor\PhpScoper
 */
class PhpScoperTest extends TestCase
{
    /**
     * @var Compactor
     */
    private $compactor;

    /**
     * @var ObjectProphecy|Scoper
     */
    private $scoperProphecy;

    /**
     * @var Scoper
     */
    private $scoper;

    /**
     * @var Configuration|ObjectProphecy
     */
    private $configProphecy;

    /**
     * @var Configuration
     */
    private $config;

    protected function setUp(): void
    {
        $this->scoperProphecy = $this->prophesize(Scoper::class);
        $this->scoper = $this->scoperProphecy->reveal();

        $this->configProphecy = $this->prophesize(Configuration::class);
        $this->config = $this->configProphecy->reveal();

        $this->compactor = new PhpScoper($this->scoper, $this->config);
    }

    public function test_it_scopes_the_file_content(): void
    {
        $file = 'foo';
        $contents = <<<'JSON'
{
    "foo": "bar"
    
}
JSON;
        $this->configProphecy->getPatchers()->willReturn([]);
        $this->configProphecy->getWhitelist()->willReturn(['Whitelisted\Foo']);

        $this->scoperProphecy
            ->scope(
                $file,
                $contents,
                Argument::containingString('_HumbugBox'),
                [],
                ['Whitelisted\Foo']
            )
            ->willReturn($expected = 'scoped')
        ;

        $actual = $this->compactor->compact($file, $contents);

        $this->assertSame($expected, $actual);

        $this->configProphecy->getPatchers()->shouldHaveBeenCalledTimes(1);
        $this->configProphecy->getWhitelist()->shouldHaveBeenCalledTimes(1);
        $this->scoperProphecy->scope(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_returns_the_content_unchanged_if_the_scoping_failed(): void
    {
        $file = 'foo';
        $contents = <<<'JSON'
{
    "foo": "bar"
    
}
JSON;
        $this->configProphecy->getPatchers()->willReturn([]);
        $this->configProphecy->getWhitelist()->willReturn(['Whitelisted\Foo']);

        $this->scoperProphecy
            ->scope(
                $file,
                $contents,
                '_HumbugBox',
                [],
                ['Whitelisted\Foo']
            )
            ->willThrow(new Error())
        ;

        $expected = $contents;

        $actual = $this->compactor->compact($file, $contents);

        $this->assertSame($expected, $actual);
    }
}
