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
use Humbug\PhpScoper\Whitelist;
use KevinGH\Box\PhpScoper\Scoper;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @covers \KevinGH\Box\Compactor\Compactors
 */
class CompactorsTest extends TestCase
{
    /** @var Compactor&ObjectProphecy */
    private $compactor1Prophecy;

    /** @var Compactor */
    private $compactor1;

    /** @var Compactor&ObjectProphecy */
    private $compactor2Prophecy;

    /** @var Compactor */
    private $compactor2;

    /** @var Compactors */
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

        $actual = $this->compactors->compact($file, $contents);

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

    /**
     * @dataProvider provideCompactorsForFirstWhitelistCheck
     *
     * @param Compactor[] $compactors
     */
    public function test_it_provides_the_first_scoper_compactor_whitelist_when_there_is_one(array $compactors, ?Whitelist $expected): void
    {
        $actual = (new Compactors(...$compactors))->getScoperWhitelist();

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideCompactorsForFirstWhitelistChange
     *
     * @param Compactor[] $compactors
     */
    public function test_it_can_change_the_first_scoper_compactor_whitelist(array $compactors, ?Whitelist $newWhitelist): void
    {
        $compactorsAggregate = new Compactors(...$compactors);

        if (null !== $newWhitelist) {
            $compactorsAggregate->registerWhitelist($newWhitelist);
        }

        $actual = $compactorsAggregate->getScoperWhitelist();

        $this->assertSame($newWhitelist, $actual);
    }

    public function test_it_is_countable(): void
    {
        $this->assertCount(0, new Compactors());
        $this->assertCount(1, new Compactors(new FakeCompactor()));
        $this->assertCount(2, new Compactors(new FakeCompactor(), new FakeCompactor()));
    }

    public function provideCompactorsForFirstWhitelistCheck(): Generator
    {
        yield [
            [],
            null,
        ];

        yield [
            [new FakeCompactor()],
            null,
        ];

        yield (function (): array {
            $whitelist = Whitelist::create(true, true, true);

            return [
                [
                    new FakeCompactor(),
                    $this->createScoperCompactor($whitelist),
                ],
                $whitelist,
            ];
        })();

        yield (function (): array {
            $firstWhitelist = Whitelist::create(true, true, true);
            $secondWhitelist = Whitelist::create(false, false, false);

            return [
                [
                    new FakeCompactor(),
                    $this->createScoperCompactor($firstWhitelist),
                    $this->createScoperCompactor($secondWhitelist),
                ],
                $firstWhitelist,
            ];
        })();
    }

    public function provideCompactorsForFirstWhitelistChange(): Generator
    {
        yield [
            [],
            null,
        ];

        yield [
            [new FakeCompactor()],
            null,
        ];

        yield (function (): array {
            $whitelist = Whitelist::create(true, true, true);

            return [
                [
                    new FakeCompactor(),
                    $this->createScoperCompactorWithChangeWhitelist($whitelist),
                ],
                $whitelist,
            ];
        })();

        yield (function (): array {
            $firstWhitelist = Whitelist::create(true, true, true);
            $secondWhitelist = Whitelist::create(false, false, false);

            return [
                [
                    new FakeCompactor(),
                    $this->createScoperCompactorWithChangeWhitelist($firstWhitelist),
                    $this->createScoperCompactorWithChangeWhitelist($secondWhitelist),
                ],
                $firstWhitelist,
            ];
        })();
    }

    private function createScoperCompactor(Whitelist $whitelist): PhpScoper
    {
        /** @var ObjectProphecy&Scoper $scoperProphecy */
        $scoperProphecy = $this->prophesize(Scoper::class);
        $scoperProphecy->getWhitelist()->willReturn($whitelist);

        /** @var Scoper $scoper */
        $scoper = $scoperProphecy->reveal();

        return new PhpScoper($scoper);
    }

    private function createScoperCompactorWithChangeWhitelist(Whitelist $whitelist): PhpScoper
    {
        /** @var ObjectProphecy&Scoper $scoperProphecy */
        $scoperProphecy = $this->prophesize(Scoper::class);
        $scoperProphecy->changeWhitelist($whitelist)->shouldBeCalled();
        $scoperProphecy->getWhitelist()->willReturn($whitelist);

        /** @var Scoper $scoper */
        $scoper = $scoperProphecy->reveal();

        return new PhpScoper($scoper);
    }
}
