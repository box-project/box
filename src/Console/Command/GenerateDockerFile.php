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
use Composer\Semver\Semver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use UnexpectedValueException;
use function array_column;
use function array_filter;
use function array_values;
use function basename;
use function file_exists;
use function getcwd;
use function implode;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\make_path_relative;
use function KevinGH\Box\FileSystem\remove;
use function realpath;
use function sprintf;
use function str_replace;
use function var_dump;

/**
 * @private
 */
final class GenerateDockerFile extends Command
{
    use CreateTemporaryPharFile;

    private const PHAR_ARG = 'phar';

    private const DOCKER_FILE_TEMPLATE = <<<'Dockerfile'
FROM php:__BASE_PHP_IMAGE_TOKEN__

RUN $(php -r '$extensionInstalled = array_map("strtolower", \get_loaded_extensions(false));$requiredExtensions = __PHP_EXTENSIONS_TOKEN__;$extensionsToInstall = array_diff($requiredExtensions, $extensionInstalled);if ([] !== $extensionsToInstall) {echo \sprintf("docker-php-ext-install %s", implode(" ", $extensionsToInstall));}echo "echo \"No extensions\"";')

COPY __PHAR_FILE_PATH_TOKEN__ /__PHAR_FILE_NAME_TOKEN__

ENTRYPOINT ["__PHAR_FILE_NAME_TOKEN__"]

Dockerfile;

    private const PHP_DOCKER_IMAGES = [
        '7.2.0' => '7.2-cli-alpine',
        '7.1.0' => '7.1-cli-alpine',
        '7.0.0' => '7-cli-alpine',
    ];

    private const DOCKER_FILE_NAME = 'Dockerfile';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('docker');
        $this->setDescription('Generates a Dockerfile for the given PHAR 🐳');
        $this->addArgument(
            self::PHAR_ARG,
            InputArgument::REQUIRED,
            'The PHAR file'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pharPath = $input->getArgument(self::PHAR_ARG);

        Assertion::file($pharPath);

        $pharPath = false !== realpath($pharPath) ? realpath($pharPath) : $pharPath;

        $io->comment(
            sprintf(
                'Generating a Dockerfile for the PHAR "<comment>%s</comment>" 🐳',
                $pharPath
            )
        );

        $tmpPharPath = $this->createTemporaryPhar($pharPath);

        $requirementsPhar = 'phar://'.$tmpPharPath.'/.box/.requirements.php';

        $dockerFileContents = self::DOCKER_FILE_TEMPLATE;

        try {
            if (false === file_exists($requirementsPhar)) {
                $io->error(
                    'Cannot retrieve the requirements for the PHAR. Make sure the PHAR has been built with Box and the '
                    .'requirement checker enabled.'
                );
            }

            $requirements = include $requirementsPhar;

            $dockerFileContents = str_replace(
                '__BASE_PHP_IMAGE_TOKEN__',
                $this->retrievePhpImageName($requirements),
                $dockerFileContents
            );

            $dockerFileContents = str_replace(
                '__PHP_EXTENSIONS_TOKEN__',
                $this->retrievePhpExtensions($requirements),
                $dockerFileContents
            );

            $dockerFileContents = str_replace(
                '__PHAR_FILE_PATH_TOKEN__',
                make_path_relative($pharPath, getcwd()),
                $dockerFileContents
            );

            $dockerFileContents = str_replace(
                '__PHAR_FILE_NAME_TOKEN__',
                basename($pharPath),
                $dockerFileContents
            );

            dump_file(self::DOCKER_FILE_NAME, $dockerFileContents);

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
            if ($tmpPharPath !== $pharPath) {
                remove($tmpPharPath);
            }
        }

        return 0;
    }

    private function retrievePhpImageName(array $requirements): string
    {
        $conditions = array_column(
            array_filter(
                $requirements,
                function (array $requirement): bool {
                    return 'php' === $requirement['type'];
                }
            ),
            'condition'
        );

        foreach (self::PHP_DOCKER_IMAGES as $php => $image) {
            foreach ($conditions as $condition) {
                if (false === Semver::satisfies($php, $condition)) {
                    continue 2;
                }
            }

            return $image;
        }

        throw new UnexpectedValueException(
            sprintf(
                'Could not find a suitable Docker base image for the PHP constraint(s) "%s". Images available: "%s"',
                implode('", "', $conditions),
                implode('", "', array_values(self::PHP_DOCKER_IMAGES))
            )
        );
    }

    private function retrievePhpExtensions(array $requirements): string
    {
        $extensions = array_column(
            array_filter(
                $requirements,
                function (array $requirement): bool {
                    return 'extension' === $requirement['type'];
                }
            ),
            'condition'
        );

        if ([] === $extensions) {
            return '[]';
        }

        return sprintf(
            '["%s"]',
            implode(
                '", "',
                $extensions
            )
        );
    }
}
