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

use Fidry\FileSystem\FS;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\ExecutableFinder;
use KevinGH\Box\MapFile;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use function iter\toArray;

final class ParallelFileProcessor
{
    /**
     * @param string[] $filePaths
     *
     * @return list<array{string, string}>
     */
    public static function processFilesInParallel(
        array $filePaths,
        MapFile $mapFile,
        Compactors $compactors,
    ): array {
        $tmp = FS::makeTmpDir('BoxProcessFile', self::class);
        FS::mkdir($tmp);

        $configPath = self::createConfig(
            $filePaths,
            $mapFile,
            $compactors,
            $tmp,
        );

        $processFilesProcess = self::createProcess($configPath, $tmp);
        $processFilesProcess->run();

        if (false === $processFilesProcess->isSuccessful()) {
            throw new ProcessFailedException($processFilesProcess);
        }

        $processedResults = self::getProcessedResults($tmp);

        $filesWithContents = array_merge(
            ...array_column($processedResults, 0),
        );
        $mergedSymbolsRegistry = SymbolsRegistry::createFromRegistries(
            array_column($processedResults, 1),
        );

        FS::remove($tmp);

        $compactors->registerSymbolsRegistry($mergedSymbolsRegistry);

        return $filesWithContents;
    }

    /**
     * @param string[] $filePaths
     *
     * @return list<array{string, string}>
     */
    private static function createConfig(
        array $filePaths,
        MapFile $mapFile,
        Compactors $compactors,
        string $tmp
    ): string {
        $config = new Configuration($filePaths, $mapFile, $compactors);
        $configPath = $tmp.'/config.json';

        FS::dumpFile($configPath, $config->serialize());

        return $configPath;
    }

    /**
     * @param array|string $configPath
     */
    private static function createProcess(
        string $configPath,
        string $tmp,
    ): Process {
        $process = new Process([
            ExecutableFinder::findPhpExecutable(),
            ExecutableFinder::findBoxExecutable(),
            ProcessFileCommand::COMMAND_NAME,
            $configPath,
            $tmp,
            '--no-interaction',
        ]);

        $process->setTimeout(3 * 60.);

        return $process;
    }

    /**
     * @return list<array{array{string, string}, SymbolsRegistry}>
     */
    public static function getProcessedResults(string $tmp): array
    {
        return array_map(
            static fn (SplFileInfo $batchResultFileInfo) => BatchResult::unserialize($batchResultFileInfo->getContents())
                ->toArray(),
            toArray(BatchResults::collect($tmp)),
        );
    }
}
