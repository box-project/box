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

use Amp\MultiReasonException;
use Assert\Assertion;
use DateTimeImmutable;
use DateTimeZone;
use KevinGH\Box\Box;
use KevinGH\Box\Compactor;
use KevinGH\Box\Configuration;
use KevinGH\Box\Console\Logger\BuildLogger;
use KevinGH\Box\MapFile;
use KevinGH\Box\PhpSettingsHandler;
use KevinGH\Box\RequirementChecker\RequirementsDumper;
use KevinGH\Box\StubGenerator;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use const DATE_ATOM;
use function array_shift;
use function count;
use function explode;
use function get_class;
use function implode;
use function KevinGH\Box\disable_parallel_processing;
use function KevinGH\Box\FileSystem\chmod;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\make_path_relative;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\FileSystem\rename;
use function KevinGH\Box\formatted_filesize;
use function KevinGH\Box\get_phar_compression_algorithms;
use function putenv;

/**
 * @final
 * @private
 * TODO: make final when Build is removed
 */
class Compile extends Configurable
{
    use ChangeableWorkingDirectory;

    private const HELP = <<<'HELP'
The <info>%command.name%</info> command will compile code in a new PHAR based on a variety of settings.
<comment>
  This command relies on a configuration file for loading
  PHAR packaging settings. If a configuration file is not
  specified through the <info>--config|-c</info> option, one of
  the following files will be used (in order): <info>box.json</info>,
  <info>box.json.dist</info>
</comment>
The configuration file is actually a JSON object saved to a file. For more
information check the documentation online:
<comment>
  https://github.com/humbug/box
</comment>
HELP;

    private const DEBUG_OPTION = 'debug';
    private const NO_PARALLEL_PROCESSING_OPTION = 'no-parallel';
    private const NO_RESTART_OPTION = 'no-restart';
    private const DEV_OPTION = 'dev';
    private const NO_CONFIG_OPTION = 'no-config';

    private const DEBUG_DIR = '.box_dump';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('compile');
        $this->setDescription('Compile an application into a PHAR');
        $this->setHelp(self::HELP);

        $this->addOption(
            self::DEBUG_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Dump the files added to the PHAR in a `'.self::DEBUG_DIR.'` directory'
        );
        $this->addOption(
            self::NO_PARALLEL_PROCESSING_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Disable the parallel processing'
        );
        $this->addOption(
            self::NO_RESTART_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Do not restart the PHP process. Box restarts the process by default to disable xdebug and set `phar.readonly=0`'
        );
        $this->addOption(
            self::DEV_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Skips the compression step'
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
            putenv('BOX_ALLOW_XDEBUG=1');
        }

        if ($debug = $input->getOption(self::DEBUG_OPTION)) {
            $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        }

        (new PhpSettingsHandler(new ConsoleLogger($output)))->check();

        if ($input->getOption(self::NO_PARALLEL_PROCESSING_OPTION)) {
            disable_parallel_processing();
            $io->writeln('<info>[debug] Disabled parallel processing</info>', OutputInterface::VERBOSITY_DEBUG);
        }

        $this->changeWorkingDirectory($input);

        $io->writeln($this->getApplication()->getHelp());
        $io->writeln('');

        $config = $input->getOption(self::NO_CONFIG_OPTION)
            ? Configuration::create(null, new stdClass())
            : $this->getConfig($input, $output, true)
        ;
        $path = $config->getOutputPath();

        $logger = new BuildLogger($io);

        $startTime = microtime(true);

        $this->removeExistingArtefacts($config, $logger, $debug);

        $logger->logStartBuilding($path);

        $this->createPhar($config, $input, $output, $logger, $io, $debug);

        $this->correctPermissions($path, $config, $logger);

        $logger->log(
            BuildLogger::STAR_PREFIX,
            'Done.'
        );

        $io->comment(
            sprintf(
                "<info>PHAR size: %s\nMemory usage: %.2fMB (peak: %.2fMB), time: %.2fs<info>",
                formatted_filesize($path),
                round(memory_get_usage() / 1024 / 1024, 2),
                round(memory_get_peak_usage() / 1024 / 1024, 2),
                round(microtime(true) - $startTime, 2)
            )
        );
    }

    private function createPhar(
        Configuration $config,
        InputInterface $input,
        OutputInterface $output,
        BuildLogger $logger,
        SymfonyStyle $io,
        bool $debug
    ): void {
        $box = Box::create(
            $config->getTmpOutputPath()
        );
        $box->getPhar()->startBuffering();

        $this->setReplacementValues($config, $box, $logger);
        $this->registerCompactors($config, $box, $logger);
        $this->registerFileMapping($config, $box, $logger);

        // Registering the main script _before_ adding the rest if of the files is _very_ important. The temporary
        // file used for debugging purposes and the Composer dump autoloading will not work correctly otherwise.
        $main = $this->registerMainScript($config, $box, $logger);

        $check = $this->registerRequirementsChecker($config, $box, $logger);

        $this->addFiles($config, $box, $logger, $io);

        $this->registerStub($config, $box, $main, $check, $logger);
        $this->configureMetadata($config, $box, $logger);

        $this->configureCompressionAlgorithm($config, $box, $input->getOption(self::DEV_OPTION), $logger);

        $box->getPhar()->stopBuffering();

        if ($debug) {
            $box->getPhar()->extractTo(self::DEBUG_DIR, null, true);
        }

        $this->signPhar($config, $box, $config->getTmpOutputPath(), $input, $output, $logger);

        if ($config->getTmpOutputPath() !== $config->getOutputPath()) {
            rename($config->getTmpOutputPath(), $config->getOutputPath());
        }
    }

    private function removeExistingArtefacts(Configuration $config, BuildLogger $logger, bool $debug): void
    {
        $path = $config->getOutputPath();

        if ($debug) {
            remove(self::DEBUG_DIR);

            $date = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
            $file = null !== $config->getFile() ? $config->getFile() : 'No config file';

            remove(self::DEBUG_DIR);

            dump_file(
                self::DEBUG_DIR.'/.box_configuration',
                <<<EOF
//
// Processed content of the configuration file "$file" dumped for debugging purposes
// Time: $date
//


EOF
                .(new CliDumper())->dump(
                    (new VarCloner())->cloneVar($config),
                    true
                )
            );
        }

        if (false === file_exists($path)) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Removing the existing PHAR "%s"',
                $path
            )
        );

        remove($path);
    }

    private function setReplacementValues(Configuration $config, Box $box, BuildLogger $logger): void
    {
        $values = $config->getProcessedReplacements();

        if ([] === $values) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Setting replacement values'
        );

        foreach ($values as $key => $value) {
            $logger->log(
                BuildLogger::PLUS_PREFIX,
                sprintf(
                    '%s: %s',
                    $key,
                    $value
                )
            );
        }

        $box->registerPlaceholders($values);
    }

    private function registerCompactors(Configuration $config, Box $box, BuildLogger $logger): void
    {
        $compactors = $config->getCompactors();

        if ([] === $compactors) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'No compactor to register'
            );

            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Registering compactors'
        );

        $logCompactors = function (Compactor $compactor) use ($logger): void {
            $compactorClassParts = explode('\\', get_class($compactor));

            if ('_HumbugBox' === substr($compactorClassParts[0], 0, strlen('_HumbugBox'))) {
                array_shift($compactorClassParts);
            }

            $logger->log(
                BuildLogger::PLUS_PREFIX,
                implode('\\', $compactorClassParts)
            );
        };

        array_map($logCompactors, $compactors);

        $box->registerCompactors($compactors);
    }

    private function registerFileMapping(Configuration $config, Box $box, BuildLogger $logger): void
    {
        $fileMapper = $config->getFileMapper();

        $this->logMap($fileMapper, $logger);

        $box->registerFileMapping(
            $config->getBasePath(),
            $fileMapper
        );
    }

    private function addFiles(Configuration $config, Box $box, BuildLogger $logger, SymfonyStyle $io): void
    {
        $logger->log(BuildLogger::QUESTION_MARK_PREFIX, 'Adding binary files');

        $count = count($config->getBinaryFiles());

        $box->addFiles($config->getBinaryFiles(), true);

        $logger->log(
            BuildLogger::CHEVRON_PREFIX,
            0 === $count
                ? 'No file found'
                : sprintf('%d file(s)', $count)
        );

        $logger->log(BuildLogger::QUESTION_MARK_PREFIX, 'Adding files');

        $count = count($config->getFiles());

        try {
            $box->addFiles($config->getFiles(), false, null !== $config->getComposerJson());
        } catch (MultiReasonException $exception) {
            // This exception is handled a different way to give me meaningful feedback to the user
            foreach ($exception->getReasons() as $reason) {
                $io->error($reason);
            }

            throw $exception;
        }

        $logger->log(
            BuildLogger::CHEVRON_PREFIX,
            0 === $count
                ? 'No file found'
                : sprintf('%d file(s)', $count)
        );
    }

    private function registerMainScript(Configuration $config, Box $box, BuildLogger $logger): ?string
    {
        $main = $config->getMainScriptPath();

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Adding main file: %s',
                $main
            )
        );

        $localMain = $box->addFile(
            $main,
            $config->getMainScriptContents()
        );

        $relativeMain = make_path_relative($main, $config->getBasePath());

        if ($localMain !== $relativeMain) {
            $logger->log(
                BuildLogger::CHEVRON_PREFIX,
                $localMain
            );
        }

        return $localMain;
    }

    private function registerRequirementsChecker(Configuration $config, Box $box, BuildLogger $logger): bool
    {
        if (false === $config->checkRequirements()) {
            return false;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Adding requirements checker'
        );

        $checkFiles = RequirementsDumper::dump($config->getComposerLockDecodedContents(), null !== $config->getCompressionAlgorithm());

        foreach ($checkFiles as $fileWithContents) {
            [$file, $contents] = $fileWithContents;

            $box->addFile('.box/'.$file, $contents, true);
        }

        return true;
    }

    private function registerStub(Configuration $config, Box $box, string $main, bool $checkRequirements, BuildLogger $logger): void
    {
        if ($config->isStubGenerated()) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Generating new stub'
            );

            $stub = $this->createStub($config, $main, $checkRequirements, $logger);

            $box->getPhar()->setStub($stub->generate());
        } elseif (null !== ($stub = $config->getStubPath())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                sprintf(
                    'Using stub file: %s',
                    $stub
                )
            );

            $box->registerStub($stub);
        } else {
            // TODO: add warning that the check requirements could not be added
            $aliasWasAdded = $box->getPhar()->setAlias($config->getAlias());

            Assertion::true(
                $aliasWasAdded,
                sprintf(
                    'The alias "%s" is invalid. See Phar::setAlias() documentation for more information.',
                    $config->getAlias()
                )
            );

            $box->getPhar()->setDefaultStub($main);

            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Using default stub'
            );
        }
    }

    private function configureMetadata(Configuration $config, Box $box, BuildLogger $logger): void
    {
        if (null !== ($metadata = $config->getMetadata())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Setting metadata'
            );

            $logger->log(
                BuildLogger::MINUS_PREFIX,
                is_string($metadata) ? $metadata : var_export($metadata, true)
            );

            $box->getPhar()->setMetadata($metadata);
        }
    }

    private function configureCompressionAlgorithm(Configuration $config, Box $box, bool $dev, BuildLogger $logger): void
    {
        if (null !== ($algorithm = $config->getCompressionAlgorithm())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                sprintf(
                    'Compressing with the algorithm "<comment>%s</comment>"',
                    array_search($algorithm, get_phar_compression_algorithms(), true)
                )
            );

            $box->getPhar()->compressFiles($algorithm);
        } else {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                $dev
                    ? 'No compression'
                    : '<error>No compression</error>'
            );
        }
    }

    private function signPhar(
        Configuration $config,
        Box $box,
        string $path,
        InputInterface $input,
        OutputInterface $output,
        BuildLogger $logger
    ): void {
        // sign using private key, if applicable
        //TODO: check that out
        remove($path.'.pubkey');

        $key = $config->getPrivateKeyPath();

        if (null === $key) {
            if (null !== ($algorithm = $config->getSigningAlgorithm())) {
                $box->getPhar()->setSignatureAlgorithm($algorithm);
            }

            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Signing using a private key'
        );

        $passphrase = $config->getPrivateKeyPassphrase();

        if ($config->isPrivateKeyPrompt()) {
            if (false === $input->isInteractive()) {
                throw new RuntimeException(
                    sprintf(
                        'Accessing to the private key "%s" requires a passphrase but none provided. Either '
                        .'provide one or run this command in interactive mode.',
                        $key
                    )
                );
            }

            /** @var $dialog QuestionHelper */
            $dialog = $this->getHelper('question');

            $question = new Question('Private key passphrase:');
            $question->setHidden(false);
            $question->setHiddenFallback(false);

            $passphrase = $dialog->ask($input, $output, $question);

            $output->writeln('');
        }

        $box->signUsingFile($key, $passphrase);
    }

    private function correctPermissions(string $path, Configuration $config, BuildLogger $logger): void
    {
        if (null !== ($chmod = $config->getFileMode())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                "Setting file permissions to <comment>$chmod</comment>"
            );

            chmod($path, $chmod);
        }
    }

    private function createStub(Configuration $config, ?string $main, bool $checkRequirements, BuildLogger $logger): StubGenerator
    {
        $stub = StubGenerator::create()
            ->alias($config->getAlias())
            ->index($main)
            ->intercept($config->isInterceptFileFuncs())
            ->checkRequirements($checkRequirements)
        ;

        if (null !== ($shebang = $config->getShebang())) {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                sprintf(
                    'Using shebang line: %s',
                    $shebang
                )
            );

            $stub->shebang($shebang);
        } else {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                'No shebang line'
            );
        }

        if (null !== ($bannerPath = $config->getStubBannerPath())) {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                sprintf(
                    'Using custom banner from file: %s',
                    $bannerPath
                )
            );

            $stub->banner($config->getStubBannerContents());
        } elseif (null !== ($banner = $config->getStubBannerContents())) {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                'Using banner:'
            );

            $bannerLines = explode("\n", $banner);

            foreach ($bannerLines as $bannerLine) {
                $logger->log(
                    BuildLogger::CHEVRON_PREFIX,
                    $bannerLine
                );
            }

            $stub->banner($banner);
        }

        return $stub;
    }

    private function logMap(MapFile $fileMapper, BuildLogger $logger): void
    {
        $map = $fileMapper->getMap();

        if ([] === $map) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Mapping paths'
        );

        foreach ($map as $item) {
            foreach ($item as $match => $replace) {
                if ('' === $match) {
                    $match = '(all)';
                    $replace .= '/';
                }

                $logger->log(
                    BuildLogger::MINUS_PREFIX,
                    sprintf(
                        '%s <info>></info> %s',
                        $match,
                        $replace
                    )
                );
            }
        }
    }
}
