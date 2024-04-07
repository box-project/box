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
use Amp\Parallel\Worker\TaskFailureThrowable;
use Amp\Parallel\Worker\WorkerPool;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Filesystem\LocalPharFile;
use KevinGH\Box\MapFile;
use function Amp\Future\await;
use function Amp\Parallel\Worker\workerPool;
use function array_chunk;

/**
 * @private
 */
final class ParallelFileProcessor
{
    public const FILE_CHUNK_SIZE = 100;

    /**
     * @param string[] $filePaths
     *
     * @throws TaskFailureThrowable
     *
     * @return LocalPharFile[]
     */
    public static function processFilesInParallel(
        array $filePaths,
        string $cwd,
        MapFile $mapFile,
        Compactors $compactors,
    ): array {
        $workerPool = workerPool();

        $result = self::queueAndWaitForTasks(
            $filePaths,
            $workerPool,
            $cwd,
            $mapFile,
            $compactors,
        );

        $compactors->registerSymbolsRegistry($result->symbolsRegistry);

        return $result->localPharFiles;
    }

    /**
     * @param string[] $filePaths
     *
     * @throws TaskFailureThrowable
     */
    public static function queueAndWaitForTasks(
        array $filePaths,
        WorkerPool $workerPool,
        string $cwd,
        MapFile $mapFile,
        Compactors $compactors,
    ): TaskResult {
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

        return TaskResult::aggregate($results);
    }
}
