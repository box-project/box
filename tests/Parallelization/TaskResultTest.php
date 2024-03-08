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

namespace KevinGH\Box\Parallelization;

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Filesystem\LocalPharFile;
use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TaskResult::class)]
final class TaskResultTest extends TestCase
{
    #[DataProvider('resultsProvider')]
    public function test_it_can_aggregate_results(
        array $results,
        TaskResult $expected,
    ): void {
        $actual = TaskResult::aggregate($results);

        self::assertEquals($expected, $actual);
    }

    public static function resultsProvider(): iterable
    {
        $symbolsRegistry1 = new SymbolsRegistry();
        $symbolsRegistry1->recordFunction(
            new FullyQualified('Acme\foo'),
            new FullyQualified('Isolated\Acme\foo'),
        );

        $symbolsRegistry2 = new SymbolsRegistry();
        $symbolsRegistry2->recordFunction(
            new FullyQualified('Acme\bar'),
            new FullyQualified('Isolated\Acme\bar'),
        );

        $expectedSymbolsRegistry = new SymbolsRegistry();
        $expectedSymbolsRegistry->recordFunction(
            new FullyQualified('Acme\foo'),
            new FullyQualified('Isolated\Acme\foo'),
        );
        $expectedSymbolsRegistry->recordFunction(
            new FullyQualified('Acme\bar'),
            new FullyQualified('Isolated\Acme\bar'),
        );

        yield [
            [
                new TaskResult(
                    [
                        new LocalPharFile('fileA', 'contentA'),
                        new LocalPharFile('fileB', 'contentB'),
                    ],
                    $symbolsRegistry1,
                ),
                new TaskResult(
                    [
                        new LocalPharFile('fileC', 'contentC'),
                        new LocalPharFile('fileD', 'contentD'),
                    ],
                    $symbolsRegistry2,
                ),
            ],
            new TaskResult(
                [
                    new LocalPharFile('fileA', 'contentA'),
                    new LocalPharFile('fileB', 'contentB'),
                    new LocalPharFile('fileC', 'contentC'),
                    new LocalPharFile('fileD', 'contentD'),
                ],
                $expectedSymbolsRegistry,
            ),
        ];
    }
}
