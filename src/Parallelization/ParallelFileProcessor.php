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

use Amp\Parallel\Worker\Execution;
use Amp\Parallel\Worker\WorkerPool;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\MapFile;
use function Amp\Future\await;
use function Amp\Parallel\Worker\workerPool;
use function array_chunk;
use function array_merge;

/**
 * @private
 */
final class ParallelFileProcessor
{
    public const FILE_CHUNK_SIZE = 100;

    /**
     * @param string[] $filePaths
     *
     * @throws MultiReasonException
     *
     * @return list<array{string, string}>
     */
    public static function processFilesInParallel(
        array $filePaths,
        string $cwd,
        MapFile $mapFile,
        Compactors $compactors,
    ): array {
        $workerPool = workerPool();

        $executions = [];

        foreach (array_chunk($filePaths, self::FILE_CHUNK_SIZE) as $filePathsChunk) {
            $executions[] = $workerPool->submit(
                new ProcessFileTask(
                    $filePathsChunk,
                    $cwd,
                    $mapFile,
                    $compactors,
                ),
            );
        }

        $results = await(
            array_map(
                static fn (Execution $execution) => $execution->getFuture(),
                $executions,
            ),
        );

        $filesWithContents = [];
        $symbolsRegistries = [];

        foreach ($results as $result) {
            $filesWithContents[] = $result->filesWithContents;
            $symbolsRegistries[] = $result->symbolsRegistry;
        }

        $compactors->registerSymbolsRegistry(
            SymbolsRegistry::createFromRegistries($symbolsRegistries),
        );

        return array_merge(...$filesWithContents);
    }
}
