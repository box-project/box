<?php

declare(strict_types=1);

namespace KevinGH\Box\Console\Command;

use Amp\MultiReasonException;
use function array_unshift;
use Assert\Assertion;
use DateTimeImmutable;
use DateTimeZone;
use function getcwd;
use KevinGH\Box\Box;
use KevinGH\Box\Compactor;
use KevinGH\Box\Compactor\Placeholder;
use KevinGH\Box\Compactors;
use KevinGH\Box\Composer\ComposerConfiguration;
use KevinGH\Box\Configuration;
use KevinGH\Box\Console\Logger\BuildLogger;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\make_path_absolute;
use KevinGH\Box\MapFile;
use KevinGH\Box\PhpSettingsHandler;
use KevinGH\Box\RequirementChecker\RequirementsDumper;
use KevinGH\Box\StubGenerator;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use const DATE_ATOM;
use const KevinGH\Box\BOX_ALLOW_XDEBUG;
use const PHP_EOL;
use const POSIX_RLIMIT_INFINITY;
use const POSIX_RLIMIT_NOFILE;
use function array_shift;
use function count;
use function decoct;
use function explode;
use function filesize;
use function function_exists;
use function get_class;
use function implode;
use function KevinGH\Box\disable_parallel_processing;
use function KevinGH\Box\FileSystem\chmod;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\make_path_relative;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\FileSystem\rename;
use function KevinGH\Box\format_size;
use function KevinGH\Box\get_phar_compression_algorithms;
use function posix_setrlimit;
use function putenv;
use function sprintf;
use function strlen;
use function substr;

final class Process extends Configurable
{
    use ChangeableWorkingDirectory;

    private const NO_RESTART_OPTION = 'no-restart';
    private const FILE_ARGUMENT = 'file';
    private const NO_CONFIG_OPTION = 'no-config';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('process');
        $this->setDescription('Apply the registered compactors and replacement values on a file');
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

        $io->writeln('');

        $config = $input->getOption(self::NO_CONFIG_OPTION)
            ? Configuration::create(null, new stdClass())
            : $this->getConfig($input, $output, true)
        ;

        $path = make_path_absolute($input->getArgument(self::FILE_ARGUMENT), getcwd());

        $compactors = $this->retrieveCompactors($config);

        $fileContents = file_contents($path);

        $io->writeln([
            sprintf(
                'Processing the contents of the file <info>%s</info>',
                $path
            ),
            '',
        ]);

        $this->logPlaceholders($io, $config);
        $this->logCompactors($io, $compactors);

        $fileProcessedContents = $compactors->compactContents($path, $fileContents);

        $io->writeln([
            'Processed contents:',
            '',
            '<comment>"""</comment>',
            $fileProcessedContents,
            '<comment>"""</comment>'
        ]);
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

        $io->writeln('');
    }

    private function logCompactors(SymfonyStyle $io, Compactors $compactors): void
    {
        $io->writeln('Registered compactors:');

        $logCompactors = function (Compactor $compactor) use ($io): void {
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

        array_map($logCompactors, $compactors->toArray());
        $io->writeln('');
    }
}