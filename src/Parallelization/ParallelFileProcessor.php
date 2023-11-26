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

use Amp\MultiReasonException;
use Fidry\FileSystem\FS;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\MapFile;
use Symfony\Component\Process\Process;
use function KevinGH\Box\register_aliases;
use function KevinGH\Box\register_error_handler;

/**
 * @private
 */
final class ParallelFileProcessor
{
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
        $processFile = static function (string $file) use ($cwd, $mapFile, $compactors): array {
            chdir($cwd);

            // Keep the fully qualified call here since this function may be executed without the right autoloading
            // mechanism
            register_aliases();
            register_error_handler();

            $contents = FS::getFileContents($file);

            $local = $mapFile($file);

            $processedContents = $compactors->compact($local, $contents);

            return [$local, $processedContents, $compactors->getScoperSymbolsRegistry()];
        };

        // In the case of parallel processing, an issue is caused due to the statefulness nature of the PhpScoper
        // symbols registry.
        //
        // Indeed, the PhpScoper symbols registry stores the records of exposed/excluded classes and functions. If nothing is done,
        // then the symbols registry retrieved in the end will here will be "blank" since the updated symbols registries are the ones
        // from the workers used for the parallel processing.
        //
        // In order to avoid that, the symbols registries will be returned as a result as well in order to be able to merge
        // all the symbols registries into one.
        //
        // This process is allowed thanks to the nature of the state of the symbols registries: having redundant classes or
        // functions registered can easily be deal with so merging all those different states is actually
        // straightforward.
        $tuples = wait(parallelMap($filePaths, $processFile));

        if ([] === $tuples) {
            return [];
        }

        $filesWithContents = [];
        $symbolRegistries = [];

        foreach ($tuples as [$local, $processedContents, $symbolRegistry]) {
            $filesWithContents[] = [$local, $processedContents];
            $symbolRegistries[] = $symbolRegistry;
        }

        $compactors->registerSymbolsRegistry(
            SymbolsRegistry::createFromRegistries(array_filter($symbolRegistries)),
        );

        return $filesWithContents;
    }
}
