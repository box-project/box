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

use function file_exists;
use function getcwd;
use KevinGH\Box\Console\IO\IO;
use function KevinGH\Box\create_temporary_phar;
use KevinGH\Box\DockerFileGenerator;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\make_path_relative;
use function KevinGH\Box\FileSystem\remove;
use function realpath;
use function sprintf;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Path;
use Webmozart\Assert\Assert;

/**
 * @private
 */
final class GenerateDockerFile extends BaseCommand
{
    public const NAME = 'docker';

    private const PHAR_ARG = 'phar';
    private const DOCKER_FILE_NAME = 'Dockerfile';

    protected function configure(): void
    {
        parent::configure();

        $this->setName(self::NAME);
        $this->setDescription('🐳  Generates a Dockerfile for the given PHAR');
        $this->addArgument(
            self::PHAR_ARG,
            InputArgument::OPTIONAL,
            'The PHAR file',
        );

        $this->getDefinition()->addOption(ConfigOption::getOptionInput());
    }

    protected function executeCommand(IO $io): int
    {
        $pharFilePath = $this->getPharFilePath($io);

        if (null === $pharFilePath) {
            return 1;
        }

        Assert::file($pharFilePath);

        $pharFilePath = false !== realpath($pharFilePath) ? realpath($pharFilePath) : $pharFilePath;

        $io->newLine();
        $io->writeln(
            sprintf(
                '🐳  Generating a Dockerfile for the PHAR "<comment>%s</comment>"',
                $pharFilePath,
            ),
        );

        $tmpPharPath = create_temporary_phar($pharFilePath);
        $cleanUp = static fn () => remove($tmpPharPath);
        $requirementsFilePhar = 'phar://'.$tmpPharPath.'/.box/.requirements.php';

        try {
            return $this->generateFile(
                $pharFilePath,
                $requirementsFilePhar,
                $io,
            );
        } finally {
            $cleanUp();
        }
    }

    /**
     * @return null|non-empty-string
     */
    private function getPharFilePath(IO $io): ?string
    {
        $pharFilePath = $io->getInput()->getArgument(self::PHAR_ARG);

        if (null === $pharFilePath) {
            $pharFilePath = $this->guessPharPath($io);
        }

        if (null === $pharFilePath) {
            return null;
        }

        $pharFilePath = Path::canonicalize($pharFilePath);
        Assert::file($pharFilePath);

        return false !== realpath($pharFilePath) ? realpath($pharFilePath) : $pharFilePath;
    }

    private function guessPharPath(IO $io): ?string
    {
        $config = ConfigOption::getConfig($io, true);

        if (file_exists($config->getOutputPath())) {
            return $config->getOutputPath();
        }

        $compile = $io->askQuestion(
            new ConfirmationQuestion(
                'The output PHAR could not be found, do you wish to generate it by running "<comment>box compile</comment>"?',
                true,
            ),
        );

        if (false === $compile) {
            $io->error('Could not find the PHAR to generate the docker file for');

            return null;
        }

        $this->getCompileCommand()->run(
            self::createCompileInput($io),
            clone $io->getOutput(),
        );

        return $config->getOutputPath();
    }

    private function getCompileCommand(): Compile
    {
        return $this->getApplication()->find(Compile::NAME);
    }

    private static function createCompileInput(IO $io): InputInterface
    {
        if ($io->isQuiet()) {
            $compileInput = '--quiet';
        } elseif ($io->isVerbose()) {
            $compileInput = '--verbose 1';
        } elseif ($io->isVeryVerbose()) {
            $compileInput = '--verbose 2';
        } elseif ($io->isDebug()) {
            $compileInput = '--verbose 3';
        } else {
            $compileInput = '';
        }

        $compileInput = new StringInput($compileInput);
        $compileInput->setInteractive(false);

        return $compileInput;
    }

    private function generateFile(string $pharFilePath, string $requirementsPhar, IO $io): int
    {
        if (false === file_exists($requirementsPhar)) {
            $io->error(
                'Cannot retrieve the requirements for the PHAR. Make sure the PHAR has been built with Box and the '
                .'requirement checker enabled.',
            );

            return 1;
        }

        $requirements = include $requirementsPhar;

        $dockerFileContents = DockerFileGenerator::createForRequirements(
            $requirements,
            make_path_relative($pharFilePath, getcwd()),
        )
            ->generateStub();

        if (file_exists(self::DOCKER_FILE_NAME)) {
            $remove = $io->askQuestion(
                new ConfirmationQuestion(
                    'A Docker file has already been found, are you sure you want to override it?',
                    true,
                ),
            );

            if (false === $remove) {
                $io->writeln('Skipped the docker file generation.');

                return 0;
            }
        }

        dump_file(self::DOCKER_FILE_NAME, $dockerFileContents);

        $io->success('Done');

        $io->writeln(
            [
                sprintf(
                    'You can now inspect your <comment>%s</comment> file or build your container with:',
                    self::DOCKER_FILE_NAME,
                ),
                '$ <comment>docker build .</comment>',
            ],
        );

        return 0;
    }
}
