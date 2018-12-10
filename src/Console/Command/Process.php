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

use KevinGH\Box\Compactor;
use KevinGH\Box\Compactor\Placeholder;
use KevinGH\Box\Compactors;
use KevinGH\Box\Configuration;
use KevinGH\Box\PhpSettingsHandler;
use stdClass;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use const KevinGH\Box\BOX_ALLOW_XDEBUG;
use function array_map;
use function array_shift;
use function array_unshift;
use function explode;
use function get_class;
use function getcwd;
use function implode;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\make_path_absolute;
use function KevinGH\Box\FileSystem\make_path_relative;
use function putenv;
use function sprintf;
use function strlen;
use function substr;

final class Process extends ConfigurableCommand
{
    use ChangeableWorkingDirectory;

    private const FILE_ARGUMENT = 'file';

    private const NO_RESTART_OPTION = 'no-restart';
    private const NO_CONFIG_OPTION = 'no-config';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('process');
        $this->setDescription('⚡  Applies the registered compactors and replacement values on a file');
        $this->setHelp(
            'The <info>%command.name%</info> command will apply the registered compactors and replacement values '
            .'on the the given file. This is useful to debug the scoping of a specific file for example.'
        );

        $this->addArgument(
            self::FILE_ARGUMENT,
            InputArgument::REQUIRED,
            'Path to the file to process'
        );
        $this->addOption(
            self::NO_RESTART_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Do not restart the PHP process. Box restarts the process by default to disable xdebug'
        );
        $this->addOption(
            self::NO_CONFIG_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Ignore the config file even when one is specified with the --config option'
        );

        $this->configureWorkingDirOption();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption(self::NO_RESTART_OPTION)) {
            putenv(BOX_ALLOW_XDEBUG.'=1');
        }

        (new PhpSettingsHandler(new ConsoleLogger($output)))->check();

        $this->changeWorkingDirectory($input);

        $io->newLine();

        $config = $input->getOption(self::NO_CONFIG_OPTION)
            ? Configuration::create(null, new stdClass())
            : $this->getConfig($input, $output, true)
        ;

        $filePath = $input->getArgument(self::FILE_ARGUMENT);

        $path = make_path_relative($filePath, $config->getBasePath());

        $compactors = $this->retrieveCompactors($config);

        $fileContents = file_contents(
            $absoluteFilePath = make_path_absolute(
                $filePath,
                getcwd()
            )
        );

        $io->writeln([
            sprintf(
                '⚡  Processing the contents of the file <info>%s</info>',
                $absoluteFilePath
            ),
            '',
        ]);

        $this->logPlaceholders($io, $config);
        $this->logCompactors($io, $compactors);

        $fileProcessedContents = $compactors->compactContents($path, $fileContents);

        if ($io->isQuiet()) {
            $io->writeln($fileProcessedContents, OutputInterface::VERBOSITY_QUIET);
        } else {
            $io->writeln([
                'Processed contents:',
                '',
                '<comment>"""</comment>',
                $fileProcessedContents,
                '<comment>"""</comment>',
            ]);
        }
    }

    private function retrieveCompactors(Configuration $config): Compactors
    {
        $compactors = $config->getCompactors();

        array_unshift(
            $compactors,
            new Placeholder($config->getReplacements())
        );

        return new Compactors(...$compactors);
    }

    private function logPlaceholders(SymfonyStyle $io, Configuration $config): void
    {
        if ([] === $config->getReplacements()) {
            $io->writeln([
                'No replacement values registered',
                '',
            ]);

            return;
        }

        $io->writeln('Registered replacement values:');

        foreach ($config->getReplacements() as $key => $value) {
            $io->writeln(
                sprintf(
                    '  <comment>+</comment> %s: %s',
                    $key,
                    $value
                )
            );
        }

        $io->newLine();
    }

    private function logCompactors(SymfonyStyle $io, Compactors $compactors): void
    {
        $nestedCompactors = $compactors->toArray();

        foreach ($nestedCompactors as $index => $compactor) {
            if ($compactor instanceof Placeholder) {
                unset($nestedCompactors[$index]);
            }
        }

        if ([] === $nestedCompactors) {
            $io->writeln([
                'No compactor registered',
                '',
            ]);

            return;
        }

        $io->writeln('Registered compactors:');

        $logCompactors = static function (Compactor $compactor) use ($io): void {
            $compactorClassParts = explode('\\', get_class($compactor));

            if ('_HumbugBox' === substr($compactorClassParts[0], 0, strlen('_HumbugBox'))) {
                // Keep the non prefixed class name for the user
                array_shift($compactorClassParts);
            }

            $io->writeln(
                sprintf(
                    '  <comment>+</comment> %s',
                    implode('\\', $compactorClassParts)
                )
            );
        };

        array_map($logCompactors, $nestedCompactors);
        $io->newLine();
    }
}
