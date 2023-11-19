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

namespace KevinGH\Box\Console\Command;

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration as ConsoleConfiguration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use Fidry\FileSystem\FS;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Annotation\DocblockAnnotationParser;
use KevinGH\Box\Box;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Compactor\FileExtensionCompactor;
use KevinGH\Box\Compactor\Json;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Compactor\Placeholder;
use KevinGH\Box\Configuration\Configuration;
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
use function array_map;
use function array_shift;
use function array_unshift;
use function explode;
use function json_encode;
use function KevinGH\Box\unique_id;
use function Safe\file_get_contents;
use function getcwd;
use function implode;
use function KevinGH\Box\check_php_settings;
use function putenv;
use function sprintf;
use function unserialize;
use const KevinGH\Box\BOX_ALLOW_XDEBUG;
use function Safe\json_decode;

final class ProcessFile extends ParallelCommand
{
    private const CONFIG_ARGUMENT = 'file';
    private const CHILD_CONFIG_ARGUMENT = 'child-file';
    private const TMP_DIR = 'tmp';

    private string $tmp;
    private string $serializedMapFile;
    private MapFile $mapFile;
    private string $serializedCompactors;
    private Compactors $compactors;
    private array $originalFilePaths = [];
    private array $processesFilesWithContents = [];

    public function __construct()
    {
        parent::__construct('internal:process:files');
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
        return $this->originalFilePaths;
    }

    protected function configureParallelExecutableFactory(ParallelExecutorFactory $parallelExecutorFactory, InputInterface $input, OutputInterface $output): ParallelExecutorFactory
    {
        return $parallelExecutorFactory
            ->withScriptPath(Box::getBoxBin())
            ->withRunBeforeFirstCommand($this->runBeforeFirstCommand(...))
            ->withRunBeforeBatch($this->runBeforeBatch(...))
            ->withRunAfterBatch($this->runAfterBatch(...))
            ->withBatchSize(100)
            ->withSegmentSize(100);
    }

    private function runBeforeFirstCommand(InputInterface $input): void
    {

        $config = json_decode(
            file_get_contents($input->getArgument(self::CONFIG_ARGUMENT)),
            true,
        );

        $this->originalFilePaths = $config['files'];
    }

    private function runBeforeBatch(InputInterface $input): void
    {
        if (isset($this->mapFile, $this->compactors, $this->tmp)) {
            return;
        }

        $config = json_decode(
            file_get_contents($input->getArgument(self::CONFIG_ARGUMENT)),
            true,
        );

        $this->mapFile = unserialize(
            $config['mapFile'],
            ['allowed_classes' => [MapFile::class]],
        );
        $this->compactors = unserialize(
            $config['compactors'],
            ['allowed_classes' => [
                Compactors::class,
                FileExtensionCompactor::class,
                Json::class,
                Php::class,
                PhpScoper::class,
                Placeholder::class,
                SerializableScoper::class,
            ]],
        );
        $this->tmp = $input->getArgument(self::TMP_DIR);
    }

    protected function runSingleCommand(string $file, InputInterface $input, OutputInterface $output): void
    {
        $contents = FS::getFileContents($file);

        $local = ($this->mapFile)($file);

        $processedContents = $this->compactors->compact($local, $contents);

        $this->processesFilesWithContents[] = [$local, $processedContents];
    }

    private function runAfterBatch(): void
    {
        FS::dumpFile(
            $this->tmp.'/'.unique_id('batch-').'.json',
            json_encode($this->processesFilesWithContents),
        );
        unset($this->processesFilesWithContents);
    }

    protected function getItemName(?int $count): string
    {
        return 1 === $count ? 'file' : 'files';
    }
}
