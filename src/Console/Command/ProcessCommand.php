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
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Compactor\Placeholder;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Console\Php\PhpSettingsChecker;
use KevinGH\Box\Constants;
use stdClass;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use function array_map;
use function array_shift;
use function array_unshift;
use function explode;
use function getcwd;
use function implode;
use function putenv;
use function sprintf;

// TODO: replace the PHP-Scoper compactor in order to warn the user about scoping errors
final class ProcessCommand implements Command
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
        if ($io->getTypedOption(self::NO_RESTART_OPTION)->asBoolean()) {
            putenv(Constants::ALLOW_XDEBUG.'=1');
        }

        PhpSettingsChecker::check($io);

        ChangeWorkingDirOption::changeWorkingDirectory($io);

        $io->newLine();

        $config = $io->getTypedOption(self::NO_CONFIG_OPTION)->asBoolean()
            ? Configuration::create(null, new stdClass())
            : ConfigOption::getConfig($io, true);

        $filePath = $io->getTypedArgument(self::FILE_ARGUMENT)->asNonEmptyString();

        $path = Path::makeRelative($filePath, $config->getBasePath());

        $compactors = self::retrieveCompactors($config);

        $fileContents = FS::getFileContents(
            $absoluteFilePath = Path::makeAbsolute(
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
            $symbolsRegistry = self::retrieveSymbolsRegistry($compactors);

            $io->writeln([
                'Processed contents:',
                '',
                '<comment>"""</comment>',
                $fileProcessedContents,
                '<comment>"""</comment>',
            ]);

            if (null !== $symbolsRegistry) {
                $io->writeln([
                    '',
                    'Symbols Registry:',
                    '',
                    '<comment>"""</comment>',
                    self::exportSymbolsRegistry($symbolsRegistry, $io),
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

    private static function retrieveSymbolsRegistry(Compactors $compactors): ?SymbolsRegistry
    {
        foreach ($compactors->toArray() as $compactor) {
            if ($compactor instanceof PhpScoper) {
                return $compactor->getScoper()->getSymbolsRegistry();
            }
        }

        return null;
    }

    private static function exportSymbolsRegistry(SymbolsRegistry $symbolsRegistry, IO $io): string
    {
        $cloner = new VarCloner();
        $cloner->setMaxItems(-1);
        $cloner->setMaxString(-1);

        $cliDumper = new CliDumper();
        if ($io->isDecorated()) {
            $cliDumper->setColors(true);
        }

        return (string) $cliDumper->dump(
            $cloner->cloneVar($symbolsRegistry),
            true,
        );
    }
}
