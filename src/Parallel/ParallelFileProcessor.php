<?php

declare(strict_types=1);

namespace KevinGH\Box\Parallel;

use Fidry\FileSystem\FS;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\BoxExecutableFinder;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Console\Command\ProcessFileCommand;
use KevinGH\Box\MapFile;
use KevinGH\Box\Phar\InvalidPhar;
use KevinGH\Box\PhpExecutableFinder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use function iter\toArray;
use function Safe\json_encode;

final class ParallelFileProcessor
{
    /**
     * @param string[] $filePaths
     *
     * @return list<array{string, string}>
     */
    public static function processFilesInParallel(
        array      $filePaths,
        MapFile    $mapFile,
        Compactors $compactors,
    ): array
    {
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

        $processedResults = array_filter(
            array_map(
                static function (SplFileInfo $batchJsonFileInfo): ?array {
                    $fileContents = FS::getFileContents($batchJsonFileInfo->getPathname());

                    $json_decode = json_decode($fileContents, true);
                    return $json_decode;
                },
                toArray(
                    Finder::create()
                        ->files()
                        ->in($tmp)
                        ->name('batch-*.json')
                ),
            ),
            static fn (?array $result) => null !== $result,
        );

        $filesWithContents = array_merge(
            ...array_column($processedResults, 'processedFilesWithContents'),
        );

        $mergedSymbolsRegistry = SymbolsRegistry::createFromRegistries(
            array_map(
                static fn (string $registry) => unserialize($registry, ['allowed_classes' => [SymbolsRegistry::class]]),
                array_column($processedResults, 'symbolsRegistry'),
            ),
        );

        FS::remove([$configPath, $tmp]);

        if (false === $processFilesProcess->isSuccessful()) {
            throw new InvalidPhar(
                $processFilesProcess->getErrorOutput(),
                $processFilesProcess->getExitCode(),
                new ProcessFailedException($processFilesProcess),
            );
        }

        $compactors->registerSymbolsRegistry($mergedSymbolsRegistry);

        return $filesWithContents;
    }

    /**
     * @param string[] $filePaths
     *
     * @return list<array{string, string}>
     */
    private static function createConfig(
        array      $filePaths,
        MapFile    $mapFile,
        Compactors $compactors,
        string $tmp
    ): string
    {
        $config = json_encode([
            'mapFile' => serialize($mapFile),
            'compactors' => serialize($compactors),
            'files' => $filePaths,
        ]);
        $configPath = $tmp.'/config.json';

        FS::dumpFile($configPath, $config);

        return $configPath;
    }

    /**
     * @param array|string $configPath
     * @param string $tmp
     * @return Process
     */
    private static function createProcess(
        string $configPath,
        string $tmp,
    ): Process
    {
        $process = new Process([
            PhpExecutableFinder::find(),
            BoxExecutableFinder::find(),
            ProcessFileCommand::COMMAND_NAME,
            $configPath,
            $tmp,
            '--no-interaction',
        ]);

        $process->setTimeout(3 * 60.);

        return $process;
    }
}