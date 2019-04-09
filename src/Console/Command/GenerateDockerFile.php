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

use Assert\Assertion;
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

/**
 * @private
 */
final class GenerateDockerFile extends ConfigurableBaseCommand
{
    private const PHAR_ARG = 'phar';
    private const DOCKER_FILE_NAME = 'Dockerfile';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('docker');
        $this->setDescription('🐳  Generates a Dockerfile for the given PHAR');
        $this->addArgument(
            self::PHAR_ARG,
            InputArgument::OPTIONAL,
            'The PHAR file'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(IO $io): int
    {
        $pharPath = $io->getInput()->getArgument(self::PHAR_ARG);

        if (null === $pharPath) {
            $pharPath = $this->guessPharPath($io);
        }

        if (null === $pharPath) {
            return 1;
        }

        Assertion::file($pharPath);

        $pharPath = false !== realpath($pharPath) ? realpath($pharPath) : $pharPath;

        $io->newLine();
        $io->writeln(
            sprintf(
                '🐳  Generating a Dockerfile for the PHAR "<comment>%s</comment>"',
                $pharPath
            )
        );

        $tmpPharPath = create_temporary_phar($pharPath);

        $requirementsPhar = 'phar://'.$tmpPharPath.'/.box/.requirements.php';

        try {
            if (false === file_exists($requirementsPhar)) {
                $io->error(
                    'Cannot retrieve the requirements for the PHAR. Make sure the PHAR has been built with Box and the '
                    .'requirement checker enabled.'
                );

                return 1;
            }

            $requirements = include $requirementsPhar;

            $dockerFileContents = DockerFileGenerator::createForRequirements(
                $requirements,
                make_path_relative($pharPath, getcwd())
                )
                ->generate()
            ;

            if (file_exists(self::DOCKER_FILE_NAME)) {
                $remove = $io->askQuestion(
                    new ConfirmationQuestion(
                        'A Docker file has already been found, are you sure you want to override it?',
                        true
                    )
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
                        self::DOCKER_FILE_NAME
                    ),
                    '$ <comment>docker build .</comment>',
                ]
            );
        } finally {
            remove($tmpPharPath);
        }

        return 0;
    }

    private function guessPharPath(IO $io): ?string
    {
        $config = $this->getConfig($io, true);

        if (file_exists($config->getOutputPath())) {
            return $config->getOutputPath();
        }

        $compile = $io->askQuestion(
            new ConfirmationQuestion(
                'The output PHAR could not be found, do you wish to generate it by running "<comment>box '
                .'compile</comment>"?',
                true
            )
        );

        if (false === $compile) {
            $io->error('Could not find the PHAR to generate the docker file for');

            return null;
        }

        $this->getCompileCommand()->run(
            $this->createCompileInput($io),
            clone $io->getOutput()
        );

        return $config->getOutputPath();
    }

    private function getCompileCommand(): Compile
    {
        return $this->getApplication()->find('compile');
    }

    private function createCompileInput(IO $io): InputInterface
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
}
