<?php

namespace KevinGH\RequirementChecker\Parallelization;

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Parallelization\TaskResult;
use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Parallelization\TaskResult
 */
final class TaskResultTest extends TestCase
{
    /**
     * @dataProvider resultsProvider
     */
    public function test_it_can_aggregate_results(
        array $results,
        TaskResult $expected,
    ): void
    {
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
                        ['fileA', 'contentA'],
                        ['fileB', 'contentB'],
                    ],
                    $symbolsRegistry1,
                ),
                new TaskResult(
                    [
                        ['fileC', 'contentC'],
                        ['fileD', 'contentD'],
                    ],
                    $symbolsRegistry2,
                ),
            ],
            new TaskResult(
                [
                    ['fileA', 'contentA'],
                    ['fileB', 'contentB'],
                    ['fileC', 'contentC'],
                    ['fileD', 'contentD'],
                ],
                $expectedSymbolsRegistry,
            ),
        ];
    }
}
