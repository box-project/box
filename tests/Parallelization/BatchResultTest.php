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
use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Parallelization\BatchResult
 * @internal
 */
final class BatchResultTest extends TestCase
{
    /**
     * @dataProvider batchResultProvider
     */
    public function test_it_can_be_serialized_and_deserialized(BatchResult $batchResult): void
    {
        $unserializedBatchResult = BatchResult::unserialize($batchResult->serialize());

        self::assertEquals($batchResult, $unserializedBatchResult);
    }

    public static function batchResultProvider(): iterable
    {
        yield 'empty' => [
            new BatchResult(
                [],
                new SymbolsRegistry(),
            ),
        ];

        $symbolsRegistry = new SymbolsRegistry();
        $symbolsRegistry->recordClass(
            new FullyQualified('Box\Func'),
            new FullyQualified('Scoped\Box\Func'),
        );

        yield 'nominal' => [
            new BatchResult(
                [
                    '/path/to/file.php',
                    '<?php echo "Hello world!";',
                ],
                $symbolsRegistry,
            ),
        ];
    }
}
