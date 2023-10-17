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

namespace KevinGH\Box\Console\Command\Composer;

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\Input\IO;
use Fidry\FileSystem\FileSystem;
use KevinGH\Box\Composer\ComposerOrchestrator;
use KevinGH\Box\Composer\ComposerProcessFactory;
use KevinGH\Box\Console\ConfigurationLoader;
use KevinGH\Box\Console\ConfigurationLocator;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @private
 */
abstract class ComposerCommand implements Command
{
    private const FILE_ARGUMENT = 'file';

    private const VERBOSITY_LEVEL_MAP = [
        LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::DEBUG => OutputInterface::VERBOSITY_VERBOSE,
    ];

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'To configure.',
            'To configure.',
            'To configure.',
            [
                new InputArgument(
                    self::FILE_ARGUMENT,
                    InputArgument::OPTIONAL,
                    'The configuration file. (default: box.json, box.json.dist)',
                ),
            ],
        );
    }

    final public function execute(IO $io): int
    {
        $composerOrchestrator = new ComposerOrchestrator(
            ComposerProcessFactory::create(
                $this->getComposerExecutable($io),
                $io,
            ),
            new ConsoleLogger($io->getOutput(), self::VERBOSITY_LEVEL_MAP),
            new FileSystem(),
        );

        return $this->orchestrate($composerOrchestrator, $io);
    }

    abstract protected function orchestrate(ComposerOrchestrator $composerOrchestrator, IO $io): int;

    private function getComposerExecutable(IO $io): ?string
    {
        try {
            $config = ConfigurationLoader::getConfig(
                $io->getArgument(self::FILE_ARGUMENT)->asNullableNonEmptyString() ?? ConfigurationLocator::findDefaultPath(),
                $io,
                false,
            );

            return $config->getComposerBin();
        } catch (RuntimeException) {
            return null;
        }
    }
}
