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

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\PhpScoper\NullScoper;
use KevinGH\Box\PhpScoper\Scoper;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophet;

/**
 * @covers \KevinGH\Box\Compactor\Compactors
 */
class CompactorsTest extends TestCase
{
    use ProphecyTrait;

    private Compactor|ObjectProphecy $compactor1Prophecy;

    private Compactor $compactor1;

    private Compactor|ObjectProphecy $compactor2Prophecy;

    private Compactor $compactor2;

    private Compactors $compactors;

    protected function setUp(): void
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
            $this->compactors->toArray(),
        );
    }

    /**
     * @dataProvider compactorsForFirstSymbolsRegistryCheckProvider
     *
     * @param list<Compactor> $compactors
     */
    public function test_it_provides_the_first_scoper_compactor_symbols_registry_when_there_is_one(
        array $compactors,
        ?SymbolsRegistry $expected,
    ): void {
        $actual = (new Compactors(...$compactors))->getScoperSymbolsRegistry();

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider compactorsForFirstSymbolsRegistryChangeProvider
     *
     * @param list<Compactor> $compactors
     */
    public function test_it_can_change_the_first_scoper_compactor_whitelist(
        array $compactors,
        ?SymbolsRegistry $newSymbolsRegistry,
    ): void {
        // We need to do this here since we use a prophet in the data provider
        $this->recordDoubledType(Scoper::class);

        $compactorsAggregate = new Compactors(...$compactors);

        if (null !== $newSymbolsRegistry) {
            $compactorsAggregate->registerSymbolsRegistry($newSymbolsRegistry);
        }

        $actual = $compactorsAggregate->getScoperSymbolsRegistry();

        $this->assertSame($newSymbolsRegistry, $actual);
    }

    public function test_it_can_change_the_symbols_registry_even_when_the_scoper_is_not_registered(): void
    {
        $compactorsAggregate = new Compactors();

        $compactorsAggregate->registerSymbolsRegistry(new SymbolsRegistry());

        $this->assertNull($compactorsAggregate->getScoperSymbolsRegistry());
    }

    public function test_it_is_countable(): void
    {
        $this->assertCount(0, new Compactors());
        $this->assertCount(1, new Compactors(new FakeCompactor()));
        $this->assertCount(2, new Compactors(new FakeCompactor(), new FakeCompactor()));
    }

    public static function compactorsForFirstSymbolsRegistryCheckProvider(): iterable
    {
        $symbolsRegistry1 = new SymbolsRegistry();
        $symbolsRegistry2 = new SymbolsRegistry();

        yield [
            [],
            null,
        ];

        yield [
            [new FakeCompactor()],
            null,
        ];

        yield [
            [
                new FakeCompactor(),
                self::createScoperCompactor($symbolsRegistry1),
            ],
            $symbolsRegistry1,
        ];

        yield [
            [
                new FakeCompactor(),
                self::createScoperCompactor($symbolsRegistry1),
                self::createScoperCompactor($symbolsRegistry2),
            ],
            $symbolsRegistry1,
        ];
    }

    public static function compactorsForFirstSymbolsRegistryChangeProvider(): iterable
    {
        $symbolsRegistry1 = new SymbolsRegistry();
        $symbolsRegistry2 = new SymbolsRegistry();

        yield [
            [],
            null,
        ];

        yield [
            [new FakeCompactor()],
            null,
        ];

        yield [
            [
                new FakeCompactor(),
                self::createScoperCompactorWithChangeSymbolsRegistry($symbolsRegistry1),
            ],
            $symbolsRegistry1,
        ];

        yield [
            [
                new FakeCompactor(),
                self::createScoperCompactorWithChangeSymbolsRegistry($symbolsRegistry1),
                self::createScoperCompactorWithChangeSymbolsRegistry($symbolsRegistry2),
            ],
            $symbolsRegistry1,
        ];
    }

    private static function createScoperCompactor(SymbolsRegistry $symbolsRegistry): PhpScoper
    {
        return new PhpScoper(new NullScoper($symbolsRegistry));
    }

    private static function createScoperCompactorWithChangeSymbolsRegistry(SymbolsRegistry $symbolsRegistry): PhpScoper
    {
        $prophet = new Prophet();

        /** @var ObjectProphecy<Scoper> $scoperProphecy */
        $scoperProphecy = $prophet->prophesize(Scoper::class);
        $scoperProphecy->changeSymbolsRegistry($symbolsRegistry)->shouldBeCalled();
        $scoperProphecy->getSymbolsRegistry()->willReturn($symbolsRegistry);

        /** @var Scoper $scoper */
        $scoper = $scoperProphecy->reveal();

        return new PhpScoper($scoper);
    }
}
