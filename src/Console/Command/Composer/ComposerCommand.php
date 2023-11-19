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
use Fidry\Console\IO;
use Fidry\FileSystem\FileSystem;
use KevinGH\Box\Composer\ComposerOrchestrator;
use KevinGH\Box\Composer\ComposerProcessFactory;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use function Safe\getcwd;

/**
 * @private
 */
abstract class ComposerCommand implements Command
{
    private const COMPOSER_BIN_OPTION = 'composer-bin';

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
            [],
            [
                new InputOption(
                    self::COMPOSER_BIN_OPTION,
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Composer executable to use.',
                ),
            ],
        );
    }

    final public function execute(IO $io): int
    {
        $composerOrchestrator = new ComposerOrchestrator(
            ComposerProcessFactory::create(
                self::getComposerExecutable($io),
                $io,
            ),
            new ConsoleLogger($io->getOutput(), self::VERBOSITY_LEVEL_MAP),
            new FileSystem(),
        );

        return $this->orchestrate($composerOrchestrator, $io);
    }

    abstract protected function orchestrate(ComposerOrchestrator $composerOrchestrator, IO $io): int;

    private static function getComposerExecutable(IO $io): ?string
    {
        $composerBin = $io->getTypedOption(self::COMPOSER_BIN_OPTION)->asNullableNonEmptyString();

        return null === $composerBin ? null : Path::makeAbsolute($composerBin, getcwd());
    }
}
