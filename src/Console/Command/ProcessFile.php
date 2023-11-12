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
use Fidry\Console\Input\IO;
use Fidry\FileSystem\FS;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Compactor\Placeholder;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\MapFile;
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
use function getcwd;
use function implode;
use function KevinGH\Box\check_php_settings;
use function putenv;
use function sprintf;
use const KevinGH\Box\BOX_ALLOW_XDEBUG;
use function Safe\json_decode;

final class ProcessFile extends ParallelCommand
{
    private const CONFIG_ARGUMENT = 'file';

    private MapFile $mapFile;
    private Compactors $compactors;
    private array $filesWithContents = [];

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

        ParallelizationInput::configureCommand($this);

        $this->setHidden();
    }

    protected function fetchItems(InputInterface $input, OutputInterface $output): iterable
    {
        $config = json_decode($input->getArgument(self::CONFIG_ARGUMENT));

        $this->mapFile = new MapFile($config['mapFile']);
        $this->compactors = new MapFile($config['compactors']);

        return $config['files'];
    }

    protected function runSingleCommand(string $file, InputInterface $input, OutputInterface $output): void
    {
        $contents = FS::getFileContents($file);

        $local = ($this->mapFile)($file);

        $processedContents = $this->compactors->compact($local, $contents);

        $this->filesWithContents[] = [$local, $processedContents];
    }

    protected function getItemName(?int $count): string
    {
        return 1 === $count ? 'file' : 'files';
    }
}
