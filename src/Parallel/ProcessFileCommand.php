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

namespace KevinGH\Box\Parallel;

use Fidry\Console\Command\Command;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use Fidry\FileSystem\FS;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Annotation\DocblockAnnotationParser;
use KevinGH\Box\Box;
use KevinGH\Box\ExecutableFinder;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Compactor\FileExtensionCompactor;
use KevinGH\Box\Compactor\Json;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Compactor\Placeholder;
use KevinGH\Box\MapFile;
use KevinGH\Box\PhpScoper\SerializableScoper;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use stdClass;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\LoggingErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ResetServiceErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ThrowableCodeErrorHandler;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use Webmozarts\Console\Parallelization\Logger\DebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\ParallelCommand;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use function array_filter;
use function array_map;
use function array_shift;
use function array_unshift;
use function count;
use function explode;
use function in_array;
use function json_encode;
use function KevinGH\Box\unique_id;
use function Safe\file_get_contents;
use function getcwd;
use function implode;
use function KevinGH\Box\check_php_settings;
use function putenv;
use function serialize;
use function sprintf;
use function unserialize;
use const KevinGH\Box\BOX_ALLOW_XDEBUG;
use function Safe\json_decode;

final class ProcessFileCommand extends ParallelCommand
{
    public const COMMAND_NAME = 'internal:process:files';

    private const SEGMENT_SIZE = 100;

    private const CONFIG_ARGUMENT = 'file';
    private const TMP_DIR = 'tmp';

    private Configuration $configuration;
    private string $tmp;
    private array $processesFilesWithContents = [];

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure(): void
    {
        $this->addArgument(
            self::CONFIG_ARGUMENT,
            InputArgument::REQUIRED,
            'Path to the file processing configuration.',
        );
        $this->addArgument(
            self::TMP_DIR,
            InputArgument::REQUIRED,
            'Temporary directory that can be used for dumping artifacts that can be collected later.',
        );

        ParallelizationInput::configureCommand($this);

        $this->setHidden();
    }

    protected function fetchItems(InputInterface $input, OutputInterface $output): iterable
    {
        if (!isset($this->configuration)) {
            $this->configuration = self::getConfiguration($input);
        }

        return $this->configuration->filePaths;
    }

    protected function configureParallelExecutableFactory(ParallelExecutorFactory $parallelExecutorFactory, InputInterface $input, OutputInterface $output): ParallelExecutorFactory
    {
        return $parallelExecutorFactory
            ->withScriptPath(ExecutableFinder::findBoxExecutable())
            // Ensure the batch & segment size are identical: we want the one and only one batch per process.
            ->withBatchSize(self::SEGMENT_SIZE)
            ->withSegmentSize(self::SEGMENT_SIZE)
            ->withRunBeforeBatch($this->runBeforeFirstBatch(...))
            ->withRunAfterBatch($this->runAfterLastBatch(...));
    }

    private function runBeforeFirstBatch(InputInterface $input): void
    {
        if (!isset($this->configuration)) {
            $this->configuration = self::getConfiguration($input);
        }

        if (!isset($this->tmp)) {
            $this->tmp = $input->getArgument(self::TMP_DIR);
        }
    }

    protected function runSingleCommand(string $file, InputInterface $input, OutputInterface $output): void
    {
        $contents = FS::getFileContents($file);

        $local = ($this->configuration->mapFile)($file);

        $processedContents = $this->configuration->compactors->compact($local, $contents);

        $this->processesFilesWithContents[] = [$local, $processedContents];
    }

    private function runAfterLastBatch(): void
    {
        $symbols = $this->configuration->compactors->getScoperSymbolsRegistry();
        $processedFilesWithContents = $this->processesFilesWithContents;

        if (count($processedFilesWithContents) === 0
            && (null === $symbols || count($symbols) === 0)
        ) {
            return;
        }

        $batchResult = new BatchResult(
            $processedFilesWithContents,
            $symbols,
        );

        FS::dumpFile(
            $this->tmp.'/'.BatchResults::createFilename(),
            $batchResult->serialize(),
        );

        $this->resetState();
    }

    protected function getItemName(?int $count): string
    {
        return 1 === $count ? 'file' : 'files';
    }

    private static function getConfiguration(InputInterface $input): Configuration
    {
        $configPath = $input->getArgument(self::CONFIG_ARGUMENT);

        return Configuration::unserialize(
            FS::getFileContents($configPath),
        );
    }

    private function resetState(): void
    {
        $this->processesFilesWithContents = [];
        $this->configuration->compactors->registerSymbolsRegistry(new SymbolsRegistry());
    }
}
