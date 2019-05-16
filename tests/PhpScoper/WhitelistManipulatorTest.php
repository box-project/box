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

use Generator;
use Humbug\PhpScoper\Whitelist;
use InvalidArgumentException;
use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\PhpScoper\WhitelistManipulator
 */
class WhitelistManipulatorTest extends TestCase
{
    public function test_it_needs_at_least_one_whitelist(): void
    {
        try {
            WhitelistManipulator::mergeWhitelists();

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Expected to have at least one whitelist, none given',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider provideWhitelistsToMerge
     *
     * @param Whitelist[] $whitelists
     */
    public function test_it_merges_whitelists_into_one(array $whitelists, Whitelist $expected): void
    {
        $actual = WhitelistManipulator::mergeWhitelists(...$whitelists);

        $this->assertEqualsCanonicalizing($expected, $actual);
        $this->assertNotSame($expected, $actual);
    }

    public function provideWhitelistsToMerge(): Generator
    {
        yield 'same whitelist' => [
            [
                Whitelist::create(true, true, true, 'Foo'),
            ],
            Whitelist::create(true, true, true, 'Foo'),
        ];

        yield 'it picks the whitelistGlobalX and whitelisted values from the first whitelist' => [
            [
                Whitelist::create(true, true, true, 'Foo'),
                Whitelist::create(false, false, false, 'Bar'),
            ],
            Whitelist::create(true, true, true, 'Foo'),
        ];

        yield 'it merges the recorded classes and functions' => [
            [
                (static function (): Whitelist {
                    $whitelist = Whitelist::create(true, true, true, 'Foo1');

                    $whitelist->recordWhitelistedClass(
                        new FullyQualified('Class1'),
                        new FullyQualified('_Box\Class1')
                    );
                    $whitelist->recordWhitelistedClass(
                        new FullyQualified('Class2'),
                        new FullyQualified('_Box\Class2')
                    );

                    $whitelist->recordWhitelistedFunction(
                        new FullyQualified('func1'),
                        new FullyQualified('_Box\func1')
                    );
                    $whitelist->recordWhitelistedClass(
                        new FullyQualified('func2'),
                        new FullyQualified('_Box\func2')
                    );

                    return $whitelist;
                })(),
                (static function (): Whitelist {
                    $whitelist = Whitelist::create(true, true, true, 'Foo2');

                    $whitelist->recordWhitelistedClass(
                        new FullyQualified('Class10'),
                        new FullyQualified('_Box\Class10')
                    );

                    $whitelist->recordWhitelistedFunction(
                        new FullyQualified('func10'),
                        new FullyQualified('_Box\func10')
                    );

                    return $whitelist;
                })(),
                (static function (): Whitelist {
                    $whitelist = Whitelist::create(true, true, true, 'Foo3');

                    $whitelist->recordWhitelistedClass(
                        new FullyQualified('Class20'),
                        new FullyQualified('_Box\Class20')
                    );

                    return $whitelist;
                })(),
                (static function (): Whitelist {
                    $whitelist = Whitelist::create(true, true, true, 'Foo4');

                    $whitelist->recordWhitelistedFunction(
                        new FullyQualified('func30'),
                        new FullyQualified('_Box\func30')
                    );

                    return $whitelist;
                })(),
            ],
            (static function (): Whitelist {
                $whitelist = Whitelist::create(true, true, true, 'Foo1');

                $whitelist->recordWhitelistedClass(
                    new FullyQualified('Class1'),
                    new FullyQualified('_Box\Class1')
                );
                $whitelist->recordWhitelistedClass(
                    new FullyQualified('Class2'),
                    new FullyQualified('_Box\Class2')
                );
                $whitelist->recordWhitelistedClass(
                    new FullyQualified('Class10'),
                    new FullyQualified('_Box\Class10')
                );
                $whitelist->recordWhitelistedClass(
                    new FullyQualified('Class20'),
                    new FullyQualified('_Box\Class20')
                );

                $whitelist->recordWhitelistedFunction(
                    new FullyQualified('func1'),
                    new FullyQualified('_Box\func1')
                );
                $whitelist->recordWhitelistedClass(
                    new FullyQualified('func2'),
                    new FullyQualified('_Box\func2')
                );
                $whitelist->recordWhitelistedFunction(
                    new FullyQualified('func10'),
                    new FullyQualified('_Box\func10')
                );
                $whitelist->recordWhitelistedFunction(
                    new FullyQualified('func30'),
                    new FullyQualified('_Box\func30')
                );

                return $whitelist;
            })(),
        ];
    }
}
