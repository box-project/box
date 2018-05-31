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

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @covers \KevinGH\Box\Compactors
 */
class CompactorsTest extends TestCase
{
    /**
     * @var Compactor|ObjectProphecy
     */
    private $compactor1Prophecy;

    /**
     * @var Compactor
     */
    private $compactor1;

    /**
     * @var Compactor|ObjectProphecy
     */
    private $compactor2Prophecy;

    /**
     * @var Compactor
     */
    private $compactor2;

    /**
     * @var Compactors
     */
    private $compactors;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        $this->compactor1Prophecy = $this->prophesize(Compactor::class);
        $this->compactor1 = $this->compactor1Prophecy->reveal();

        $this->compactor2Prophecy = $this->prophesize(Compactor::class);
        $this->compactor2 = $this->compactor2Prophecy->reveal();

        $this->compactors = new Compactors($this->compactor1, $this->compactor2);
    }

    public function test_it_applies_all_compactors_in_order(): void
    {
        $file = 'foo';
        $contents = 'original contents';

        $this->compactor1Prophecy
            ->compact($file, $contents)
            ->willReturn($contentsAfterCompactor1 = 'contents after compactor1')
        ;
        $this->compactor2Prophecy
            ->compact($file, $contentsAfterCompactor1)
            ->willReturn($contentsAfterCompactor2 = 'contents after compactor2')
        ;

        $expected = $contentsAfterCompactor2;

        $actual = $this->compactors->compactContents($file, $contents);

        $this->assertSame($expected, $actual);

        $this->compactor1Prophecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
        $this->compactor2Prophecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_can_be_converted_into_an_array(): void
    {
        $this->assertSame(
            [
                $this->compactor1,
                $this->compactor2,
            ],
            $this->compactors->toArray()
        );
    }
}
