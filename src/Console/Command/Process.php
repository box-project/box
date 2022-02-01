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

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use function array_map;
use function array_shift;
use function array_unshift;
use function explode;
use function get_class;
use function getcwd;
use function implode;
use const KevinGH\Box\BOX_ALLOW_XDEBUG;
use function KevinGH\Box\check_php_settings;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Compactor\Placeholder;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Console\IO\IO;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\make_path_absolute;
use function KevinGH\Box\FileSystem\make_path_relative;
use function putenv;
use function sprintf;
use stdClass;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

final class Process extends ConfigurableBaseCommand
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
    protected function executeCommand(IO $io): int
    {
        $input = $io->getInput();

        if ($input->getOption(self::NO_RESTART_OPTION)) {
            putenv(BOX_ALLOW_XDEBUG.'=1');
        }

        check_php_settings($io);

        $this->changeWorkingDirectory($input);

        $io->newLine();

        $config = $input->getOption(self::NO_CONFIG_OPTION)
            ? Configuration::create(null, new stdClass())
            : $this->getConfig($io, true)
        ;

        /** @var string $filePath */
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

        $this->logPlaceholders($config, $io);
        $this->logCompactors($compactors, $io);

        $fileProcessedContents = $compactors->compact($path, $fileContents);

        if ($io->isQuiet()) {
            $io->writeln($fileProcessedContents, OutputInterface::VERBOSITY_QUIET);
        } else {
            $whitelist = $this->retrieveWhitelist($compactors);

            $io->writeln([
                'Processed contents:',
                '',
                '<comment>"""</comment>',
                $fileProcessedContents,
                '<comment>"""</comment>',
            ]);

            if (null !== $whitelist) {
                $io->writeln([
                    '',
                    'Whitelist:',
                    '',
                    '<comment>"""</comment>',
                    $this->exportWhitelist($whitelist, $io),
                    '<comment>"""</comment>',
                ]);
            }
        }

        return 0;
    }

    private function retrieveCompactors(Configuration $config): Compactors
    {
        $compactors = $config->getCompactors()->toArray();

        array_unshift(
            $compactors,
            new Placeholder($config->getReplacements())
        );

        return new Compactors(...$compactors);
    }

    private function logPlaceholders(Configuration $config, IO $io): void
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

    private function logCompactors(Compactors $compactors, IO $io): void
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

            if (0 === strpos($compactorClassParts[0], '_HumbugBox')) {
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

    private function retrieveWhitelist(Compactors $compactors): ?SymbolsRegistry
    {
        foreach ($compactors->toArray() as $compactor) {
            if ($compactor instanceof PhpScoper) {
                return $compactor->getScoper()->getSymbolsRegistry();
            }
        }

        return null;
    }

    private function exportWhitelist(SymbolsRegistry $whitelist, IO $io): string
    {
        $cloner = new VarCloner();
        $cloner->setMaxItems(-1);
        $cloner->setMaxString(-1);

        $cliDumper = new CliDumper();
        if ($io->isDecorated()) {
            $cliDumper->setColors(true);
        }

        return (string) $cliDumper->dump(
            $cloner->cloneVar($whitelist),
            true
        );
    }
}
