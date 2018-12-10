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
use KevinGH\Box\Composer\ComposerConfiguration;
use KevinGH\Box\Configuration;
use KevinGH\Box\Console\Logger\CompileLogger;
use KevinGH\Box\Console\MessageRenderer;
use KevinGH\Box\Console\OutputConfigurator;
use KevinGH\Box\MapFile;
use KevinGH\Box\PhpSettingsHandler;
use KevinGH\Box\RequirementChecker\RequirementsDumper;
use KevinGH\Box\StubGenerator;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
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
use function array_map;
use function array_search;
use function array_shift;
use function count;
use function decoct;
use function explode;
use function file_exists;
use function filesize;
use function function_exists;
use function get_class;
use function implode;
use function is_string;
use function KevinGH\Box\disable_parallel_processing;
use function KevinGH\Box\FileSystem\chmod;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\make_path_relative;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\FileSystem\rename;
use function KevinGH\Box\format_size;
use function KevinGH\Box\get_phar_compression_algorithms;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function posix_getrlimit;
use function posix_setrlimit;
use function putenv;
use function round;
use function sprintf;
use function strlen;
use function substr;
use function var_export;

/**
 * @final
 * @private
 */
class Compile extends ConfigurableCommand
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
    private const WITH_DOCKER_OPTION = 'with-docker';

    private const DEBUG_DIR = '.box_dump';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('compile');
        $this->setDescription('🔨  Compiles an application into a PHAR');
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
        $this->addOption(
            self::WITH_DOCKER_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Generates a Dockerfile'
        );

        $this->configureWorkingDirOption();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        OutputConfigurator::configure($output);

        if ($input->getOption(self::NO_RESTART_OPTION)) {
            putenv(BOX_ALLOW_XDEBUG.'=1');
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
        $io->newLine();

        $config = $input->getOption(self::NO_CONFIG_OPTION)
            ? Configuration::create(null, new stdClass())
            : $this->getConfig($input, $output, true)
        ;
        $path = $config->getOutputPath();

        $logger = new CompileLogger($io);

        $startTime = microtime(true);

        $logger->logStartBuilding($path);

        $this->removeExistingArtifacts($config, $logger, $debug);

        $box = $this->createPhar($config, $input, $output, $logger, $io, $debug);

        $this->correctPermissions($path, $config, $logger);

        $this->logEndBuilding($config, $logger, $io, $box, $path, $startTime);

        if ($input->getOption(self::WITH_DOCKER_OPTION)) {
            $this->generateDockerFile($output);
        }
    }

    private function createPhar(
        Configuration $config,
        InputInterface $input,
        OutputInterface $output,
        CompileLogger $logger,
        SymfonyStyle $io,
        bool $debug
    ): Box {
        $box = Box::create(
            $config->getTmpOutputPath()
        );
        $box->startBuffering();

        $this->registerReplacementValues($config, $box, $logger);
        $this->registerCompactors($config, $box, $logger);
        $this->registerFileMapping($config, $box, $logger);

        // Registering the main script _before_ adding the rest if of the files is _very_ important. The temporary
        // file used for debugging purposes and the Composer dump autoloading will not work correctly otherwise.
        $main = $this->registerMainScript($config, $box, $logger);

        $check = $this->registerRequirementsChecker($config, $box, $logger);

        $this->addFiles($config, $box, $logger, $io);

        $this->registerStub($config, $box, $main, $check, $logger);
        $this->configureMetadata($config, $box, $logger);

        $this->commit($box, $config, $logger);

        $this->checkComposerFiles($box, $config, $logger);

        if ($debug) {
            $box->getPhar()->extractTo(self::DEBUG_DIR, null, true);
        }

        $this->configureCompressionAlgorithm($config, $box, $input->getOption(self::DEV_OPTION), $io, $logger);

        $this->signPhar($config, $box, $config->getTmpOutputPath(), $input, $output, $logger);

        if ($config->getTmpOutputPath() !== $config->getOutputPath()) {
            rename($config->getTmpOutputPath(), $config->getOutputPath());
        }

        return $box;
    }

    private function removeExistingArtifacts(Configuration $config, CompileLogger $logger, bool $debug): void
    {
        $path = $config->getOutputPath();

        if ($debug) {
            remove(self::DEBUG_DIR);

            $date = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
            $file = $config->getConfigurationFile() ?? 'No config file';

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
            CompileLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Removing the existing PHAR "%s"',
                $path
            )
        );

        remove($path);
    }

    private function registerReplacementValues(Configuration $config, Box $box, CompileLogger $logger): void
    {
        $values = $config->getReplacements();

        if ([] === $values) {
            return;
        }

        $logger->log(
            CompileLogger::QUESTION_MARK_PREFIX,
            'Setting replacement values'
        );

        foreach ($values as $key => $value) {
            $logger->log(
                CompileLogger::PLUS_PREFIX,
                sprintf(
                    '%s: %s',
                    $key,
                    $value
                )
            );
        }

        $box->registerPlaceholders($values);
    }

    private function registerCompactors(Configuration $config, Box $box, CompileLogger $logger): void
    {
        $compactors = $config->getCompactors();

        if ([] === $compactors) {
            $logger->log(
                CompileLogger::QUESTION_MARK_PREFIX,
                'No compactor to register'
            );

            return;
        }

        $logger->log(
            CompileLogger::QUESTION_MARK_PREFIX,
            'Registering compactors'
        );

        $logCompactors = static function (Compactor $compactor) use ($logger): void {
            $compactorClassParts = explode('\\', get_class($compactor));

            if ('_HumbugBox' === substr($compactorClassParts[0], 0, strlen('_HumbugBox'))) {
                // Keep the non prefixed class name for the user
                array_shift($compactorClassParts);
            }

            $logger->log(
                CompileLogger::PLUS_PREFIX,
                implode('\\', $compactorClassParts)
            );
        };

        array_map($logCompactors, $compactors);

        $box->registerCompactors($compactors);
    }

    private function registerFileMapping(Configuration $config, Box $box, CompileLogger $logger): void
    {
        $fileMapper = $config->getFileMapper();

        $this->logMap($fileMapper, $logger);

        $box->registerFileMapping($fileMapper);
    }

    private function addFiles(Configuration $config, Box $box, CompileLogger $logger, SymfonyStyle $io): void
    {
        $logger->log(CompileLogger::QUESTION_MARK_PREFIX, 'Adding binary files');

        $count = count($config->getBinaryFiles());

        $box->addFiles($config->getBinaryFiles(), true);

        $logger->log(
            CompileLogger::CHEVRON_PREFIX,
            0 === $count
                ? 'No file found'
                : sprintf('%d file(s)', $count)
        );

        $logger->log(
            CompileLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Auto-discover files? %s',
                $config->hasAutodiscoveredFiles() ? 'Yes' : 'No'
            )
        );
        $logger->log(CompileLogger::QUESTION_MARK_PREFIX, 'Adding files');

        $count = count($config->getFiles());

        try {
            $box->addFiles($config->getFiles(), false);
        } catch (MultiReasonException $exception) {
            // This exception is handled a different way to give me meaningful feedback to the user
            foreach ($exception->getReasons() as $reason) {
                $io->error($reason);
            }

            throw $exception;
        }

        $logger->log(
            CompileLogger::CHEVRON_PREFIX,
            0 === $count
                ? 'No file found'
                : sprintf('%d file(s)', $count)
        );
    }

    private function registerMainScript(Configuration $config, Box $box, CompileLogger $logger): ?string
    {
        if (false === $config->hasMainScript()) {
            $logger->log(
                CompileLogger::QUESTION_MARK_PREFIX,
                'No main script path configured'
            );

            return null;
        }

        $main = $config->getMainScriptPath();

        $logger->log(
            CompileLogger::QUESTION_MARK_PREFIX,
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
                CompileLogger::CHEVRON_PREFIX,
                $localMain
            );
        }

        return $localMain;
    }

    private function registerRequirementsChecker(Configuration $config, Box $box, CompileLogger $logger): bool
    {
        if (false === $config->checkRequirements()) {
            $logger->log(
                CompileLogger::QUESTION_MARK_PREFIX,
                'Skip requirements checker'
            );

            return false;
        }

        $logger->log(
            CompileLogger::QUESTION_MARK_PREFIX,
            'Adding requirements checker'
        );

        $checkFiles = RequirementsDumper::dump(
            $config->getDecodedComposerJsonContents() ?? [],
            $config->getDecodedComposerLockContents() ?? [],
            $config->getCompressionAlgorithm()
        );

        foreach ($checkFiles as $fileWithContents) {
            [$file, $contents] = $fileWithContents;

            $box->addFile('.box/'.$file, $contents, true);
        }

        return true;
    }

    private function registerStub(Configuration $config, Box $box, ?string $main, bool $checkRequirements, CompileLogger $logger): void
    {
        if ($config->isStubGenerated()) {
            $logger->log(
                CompileLogger::QUESTION_MARK_PREFIX,
                'Generating new stub'
            );

            $stub = $this->createStub($config, $main, $checkRequirements, $logger);

            $box->getPhar()->setStub($stub);

            return;
        }

        if (null !== ($stub = $config->getStubPath())) {
            $logger->log(
                CompileLogger::QUESTION_MARK_PREFIX,
                sprintf(
                    'Using stub file: %s',
                    $stub
                )
            );

            $box->registerStub($stub);

            return;
        }

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
            CompileLogger::QUESTION_MARK_PREFIX,
            'Using default stub'
        );
    }

    private function configureMetadata(Configuration $config, Box $box, CompileLogger $logger): void
    {
        if (null !== ($metadata = $config->getMetadata())) {
            $logger->log(
                CompileLogger::QUESTION_MARK_PREFIX,
                'Setting metadata'
            );

            $logger->log(
                CompileLogger::MINUS_PREFIX,
                is_string($metadata) ? $metadata : var_export($metadata, true)
            );

            $box->getPhar()->setMetadata($metadata);
        }
    }

    private function commit(Box $box, Configuration $config, CompileLogger $logger): void
    {
        $message = $config->dumpAutoload()
            ? 'Dumping the Composer autoloader'
            : 'Skipping dumping the Composer autoloader'
        ;

        $logger->log(CompileLogger::QUESTION_MARK_PREFIX, $message);

        $box->endBuffering($config->dumpAutoload());
    }

    private function checkComposerFiles(Box $box, Configuration $config, CompileLogger $logger): void
    {
        $message = $config->excludeComposerFiles()
            ? 'Removing the Composer dump artefacts'
            : 'Keep the Composer dump artefacts'
        ;

        $logger->log(CompileLogger::QUESTION_MARK_PREFIX, $message);

        if ($config->excludeComposerFiles()) {
            $box->removeComposerArtefacts(
                ComposerConfiguration::retrieveVendorDir(
                    $config->getDecodedComposerJsonContents() ?? []
                )
            );
        }
    }

    private function configureCompressionAlgorithm(Configuration $config, Box $box, bool $dev, SymfonyStyle $io, CompileLogger $logger): void
    {
        if (null === ($algorithm = $config->getCompressionAlgorithm())) {
            $logger->log(
                CompileLogger::QUESTION_MARK_PREFIX,
                'No compression'
            );

            return;
        }

        if ($dev) {
            $logger->log(CompileLogger::QUESTION_MARK_PREFIX, 'Dev mode detected: skipping the compression');

            return;
        }

        $logger->log(
            CompileLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Compressing with the algorithm "<comment>%s</comment>"',
                array_search($algorithm, get_phar_compression_algorithms(), true)
            )
        );

        $restoreLimit = self::bumpOpenFileDescriptorLimit($box, $io);

        try {
            $extension = $box->compress($algorithm);

            if (null !== $extension) {
                $logger->log(
                    CompileLogger::CHEVRON_PREFIX,
                    sprintf(
                        '<info>Warning: the extension "%s" will now be required to execute the PHAR</info>',
                        $extension
                    )
                );
            }
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            // Continue: the compression failure should not result in completely bailing out the compilation process
        } finally {
            $restoreLimit();
        }
    }

    /**
     * Bumps the maximum number of open file descriptor if necessary.
     *
     * @return callable callable to call to restore the original maximum number of open files descriptors
     */
    private static function bumpOpenFileDescriptorLimit(Box $box, SymfonyStyle $io): callable
    {
        $filesCount = count($box) + 128;  // Add a little extra for good measure

        if (false === function_exists('posix_getrlimit') || false === function_exists('posix_setrlimit')) {
            $io->writeln(
                '<info>[debug] Could not check the maximum number of open file descriptors: the functions "posix_getrlimit()" and '
                .'"posix_setrlimit" could not be found.</info>',
                OutputInterface::VERBOSITY_DEBUG
            );

            return static function (): void {};
        }

        $softLimit = posix_getrlimit()['soft openfiles'];
        $hardLimit = posix_getrlimit()['hard openfiles'];

        if ($softLimit >= $filesCount) {
            return static function (): void {};
        }

        $io->writeln(
            sprintf(
                '<info>[debug] Increased the maximum number of open file descriptors from ("%s", "%s") to ("%s", "%s")'
                .'</info>',
                $softLimit,
                $hardLimit,
                $filesCount,
                'unlimited'
            ),
            OutputInterface::VERBOSITY_DEBUG
        );

        posix_setrlimit(
            POSIX_RLIMIT_NOFILE,
            $filesCount,
            'unlimited' === $hardLimit ? POSIX_RLIMIT_INFINITY : $hardLimit
        );

        return static function () use ($io, $softLimit, $hardLimit): void {
            if (function_exists('posix_setrlimit') && isset($softLimit, $hardLimit)) {
                posix_setrlimit(
                    POSIX_RLIMIT_NOFILE,
                    $softLimit,
                    'unlimited' === $hardLimit ? POSIX_RLIMIT_INFINITY : $hardLimit
                );

                $io->writeln(
                    '<info>[debug] Restored the maximum number of open file descriptors</info>',
                    OutputInterface::VERBOSITY_DEBUG
                );
            }
        };
    }

    private function signPhar(
        Configuration $config,
        Box $box,
        string $path,
        InputInterface $input,
        OutputInterface $output,
        CompileLogger $logger
    ): void {
        // Sign using private key when applicable
        remove($path.'.pubkey');

        $key = $config->getPrivateKeyPath();

        if (null === $key) {
            if (null !== ($algorithm = $config->getSigningAlgorithm())) {
                $box->getPhar()->setSignatureAlgorithm($algorithm);
            }

            return;
        }

        $logger->log(
            CompileLogger::QUESTION_MARK_PREFIX,
            'Signing using a private key'
        );

        $passphrase = $config->getPrivateKeyPassphrase();

        if ($config->promptForPrivateKey()) {
            if (false === $input->isInteractive()) {
                throw new RuntimeException(
                    sprintf(
                        'Accessing to the private key "%s" requires a passphrase but none provided. Either '
                        .'provide one or run this command in interactive mode.',
                        $key
                    )
                );
            }

            /** @var QuestionHelper $dialog */
            $dialog = $this->getHelper('question');

            $question = new Question('Private key passphrase:');
            $question->setHidden(false);
            $question->setHiddenFallback(false);

            $passphrase = $dialog->ask($input, $output, $question);

            $output->writeln('');
        }

        $box->signUsingFile($key, $passphrase);
    }

    private function correctPermissions(string $path, Configuration $config, CompileLogger $logger): void
    {
        if (null !== ($chmod = $config->getFileMode())) {
            $logger->log(
                CompileLogger::QUESTION_MARK_PREFIX,
                sprintf(
                    'Setting file permissions to <comment>%s</comment>',
                    '0'.decoct($chmod)
                )
            );

            chmod($path, $chmod);
        }
    }

    private function createStub(Configuration $config, ?string $main, bool $checkRequirements, CompileLogger $logger): string
    {
        $stub = StubGenerator::create()
            ->alias($config->getAlias())
            ->index($main)
            ->intercept($config->isInterceptFileFuncs())
            ->checkRequirements($checkRequirements)
        ;

        if (null !== ($shebang = $config->getShebang())) {
            $logger->log(
                CompileLogger::MINUS_PREFIX,
                sprintf(
                    'Using shebang line: %s',
                    $shebang
                )
            );

            $stub->shebang($shebang);
        } else {
            $logger->log(
                CompileLogger::MINUS_PREFIX,
                'No shebang line'
            );
        }

        if (null !== ($bannerPath = $config->getStubBannerPath())) {
            $logger->log(
                CompileLogger::MINUS_PREFIX,
                sprintf(
                    'Using custom banner from file: %s',
                    $bannerPath
                )
            );

            $stub->banner($config->getStubBannerContents());
        } elseif (null !== ($banner = $config->getStubBannerContents())) {
            $logger->log(
                CompileLogger::MINUS_PREFIX,
                'Using banner:'
            );

            $bannerLines = explode("\n", $banner);

            foreach ($bannerLines as $bannerLine) {
                $logger->log(
                    CompileLogger::CHEVRON_PREFIX,
                    $bannerLine
                );
            }

            $stub->banner($banner);
        }

        return $stub->generate();
    }

    private function logMap(MapFile $fileMapper, CompileLogger $logger): void
    {
        $map = $fileMapper->getMap();

        if ([] === $map) {
            return;
        }

        $logger->log(
            CompileLogger::QUESTION_MARK_PREFIX,
            'Mapping paths'
        );

        foreach ($map as $item) {
            foreach ($item as $match => $replace) {
                if ('' === $match) {
                    $match = '(all)';
                    $replace .= '/';
                }

                $logger->log(
                    CompileLogger::MINUS_PREFIX,
                    sprintf(
                        '%s <info>></info> %s',
                        $match,
                        $replace
                    )
                );
            }
        }
    }

    private function logEndBuilding(
        Configuration $config,
        CompileLogger $logger,
        SymfonyStyle $io,
        Box $box,
        string $path,
        float $startTime
    ): void {
        $logger->log(
            CompileLogger::STAR_PREFIX,
            'Done.'
        );
        $io->newLine();

        MessageRenderer::render($io, $config->getRecommendations(), $config->getWarnings());

        $io->comment(
            sprintf(
                'PHAR: %s (%s)',
                $box->count() > 1 ? $box->count().' files' : $box->count().' file',
                format_size(
                    filesize($path)
                )
            )
            .PHP_EOL
            .'You can inspect the generated PHAR with the "<comment>info</comment>" command.'
        );

        $io->comment(
            sprintf(
                '<info>Memory usage: %.2fMB (peak: %.2fMB), time: %.2fs<info>',
                round(memory_get_usage() / 1024 / 1024, 2),
                round(memory_get_peak_usage() / 1024 / 1024, 2),
                round(microtime(true) - $startTime, 2)
            )
        );
    }

    private function generateDockerFile(OutputInterface $output): void
    {
        $generateDockerFileCommand = $this->getApplication()->find('docker');

        $input = new StringInput('');
        $input->setInteractive(false);

        $generateDockerFileCommand->run($input, $output);
    }
}
