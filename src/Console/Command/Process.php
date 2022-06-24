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

use function array_map;
use function array_shift;
use function array_unshift;
use function explode;
use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration as ConsoleConfiguration;
use Fidry\Console\ExitCode;
use Fidry\Console\Input\IO;
use function getcwd;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use function implode;
use const KevinGH\Box\BOX_ALLOW_XDEBUG;
use function KevinGH\Box\check_php_settings;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Compactor\Placeholder;
use KevinGH\Box\Configuration\Configuration;
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

// TODO: replace the PHP-Scoper compactor in order to warn the user about scoping errors
final class Process implements Command
{
    private const FILE_ARGUMENT = 'file';

    private const NO_RESTART_OPTION = 'no-restart';
    private const NO_CONFIG_OPTION = 'no-config';

    public function getConfiguration(): ConsoleConfiguration
    {
        return new ConsoleConfiguration(
            'process',
            '⚡  Applies the registered compactors and replacement values on a file',
            'The <info>%command.name%</info> command will apply the registered compactors and replacement values on the the given file. This is useful to debug the scoping of a specific file for example.',
            [
                new InputArgument(
                    self::FILE_ARGUMENT,
                    InputArgument::REQUIRED,
                    'Path to the file to process',
                ),
            ],
            [
                new InputOption(
                    self::NO_RESTART_OPTION,
                    null,
                    InputOption::VALUE_NONE,
                    'Do not restart the PHP process. Box restarts the process by default to disable xdebug',
                ),
                new InputOption(
                    self::NO_CONFIG_OPTION,
                    null,
                    InputOption::VALUE_NONE,
                    'Ignore the config file even when one is specified with the --config option',
                ),
                ConfigOption::getOptionInput(),
                ChangeWorkingDirOption::getOptionInput(),
            ],
        );
    }

    public function execute(IO $io): int
    {
        if ($io->getOption(self::NO_RESTART_OPTION)->asBoolean()) {
            putenv(BOX_ALLOW_XDEBUG.'=1');
        }

        check_php_settings($io);

        ChangeWorkingDirOption::changeWorkingDirectory($io);

        $io->newLine();

        $config = $io->getOption(self::NO_CONFIG_OPTION)->asBoolean()
            ? Configuration::create(null, new stdClass())
            : ConfigOption::getConfig($io, true)
        ;

        $filePath = $io->getArgument(self::FILE_ARGUMENT)->asNonEmptyString();

        $path = make_path_relative($filePath, $config->getBasePath());

        $compactors = self::retrieveCompactors($config);

        $fileContents = file_contents(
            $absoluteFilePath = make_path_absolute(
                $filePath,
                getcwd(),
            ),
        );

        $io->writeln([
            sprintf(
                '⚡  Processing the contents of the file <info>%s</info>',
                $absoluteFilePath,
            ),
            '',
        ]);

        self::logPlaceholders($config, $io);
        self::logCompactors($compactors, $io);

        $fileProcessedContents = $compactors->compact($path, $fileContents);

        if ($io->isQuiet()) {
            $io->writeln($fileProcessedContents, OutputInterface::VERBOSITY_QUIET);
        } else {
            $whitelist = self::retrieveWhitelist($compactors);

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
                    self::exportWhitelist($whitelist, $io),
                    '<comment>"""</comment>',
                ]);
            }
        }

        return ExitCode::SUCCESS;
    }

    private static function retrieveCompactors(Configuration $config): Compactors
    {
        $compactors = $config->getCompactors()->toArray();

        array_unshift(
            $compactors,
            new Placeholder($config->getReplacements()),
        );

        return new Compactors(...$compactors);
    }

    private static function logPlaceholders(Configuration $config, IO $io): void
    {
        if (0 === count($config->getReplacements())) {
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
                    $value,
                ),
            );
        }

        $io->newLine();
    }

    private static function logCompactors(Compactors $compactors, IO $io): void
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
            $compactorClassParts = explode('\\', $compactor::class);

            if (str_starts_with($compactorClassParts[0], '_HumbugBox')) {
                // Keep the non prefixed class name for the user
                array_shift($compactorClassParts);
            }

            $io->writeln(
                sprintf(
                    '  <comment>+</comment> %s',
                    implode('\\', $compactorClassParts),
                ),
            );
        };

        array_map($logCompactors, $nestedCompactors);

        $io->newLine();
    }

    private static function retrieveWhitelist(Compactors $compactors): ?SymbolsRegistry
    {
        foreach ($compactors->toArray() as $compactor) {
            if ($compactor instanceof PhpScoper) {
                return $compactor->getScoper()->getSymbolsRegistry();
            }
        }

        return null;
    }

    private static function exportWhitelist(SymbolsRegistry $whitelist, IO $io): string
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
            true,
        );
    }
}
