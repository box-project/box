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

use Exception;
use KevinGH\Box\Console\ConfigurationLoader;
use KevinGH\Box\Console\IO\IO;
use KevinGH\Box\Console\MessageRenderer;
use KevinGH\Box\Json\JsonValidationException;
use function sprintf;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * @private
 */
final class Validate extends BaseCommand
{
    private const FILE_ARGUMENT = 'file';
    private const IGNORE_MESSAGES_OPTION = 'ignore-recommendations-and-warnings';

    protected function configure(): void
    {
        parent::configure();

        $this->setName('validate');
        $this->setDescription('⚙  Validates the configuration file');
        $this->setHelp(
            <<<'HELP'
                The <info>%command.name%</info> command will validate the configuration file
                and report any errors found, if any.
                <comment>
                  This command relies on a configuration file for loading
                  PHAR packaging settings. If a configuration file is not
                  specified through the <info>--configuration|-c</info> option, one of
                  the following files will be used (in order): <info>box.json,
                  box.json.dist</info>
                </comment>
                HELP
        );
        $this->addArgument(
            self::FILE_ARGUMENT,
            InputArgument::OPTIONAL,
            'The configuration file. (default: box.json, box.json.dist)'
        );
        $this->addOption(
            self::IGNORE_MESSAGES_OPTION,
            'i',
            InputOption::VALUE_NONE,
            'Will not return a faulty code when a recommendation or warning is found'
        );
    }

    protected function executeCommand(IO $io): int
    {
        $input = $io->getInput();

        try {
            $config = ConfigurationLoader::getConfig(
                $input->getArgument(self::FILE_ARGUMENT) ?? $this->getConfigurationHelper()->findDefaultPath(),
                $this->getConfigurationHelper(),
                $io,
                false
            );

            $recommendations = $config->getRecommendations();
            $warnings = $config->getWarnings();

            MessageRenderer::render($io, $recommendations, $warnings);

            $hasRecommendationsOrWarnings = [] === $recommendations && [] === $warnings;

            if (false === $hasRecommendationsOrWarnings) {
                if ([] === $recommendations) {
                    $io->caution('The configuration file passed the validation with warnings.');
                } elseif ([] === $warnings) {
                    $io->caution('The configuration file passed the validation with recommendations.');
                } else {
                    $io->caution('The configuration file passed the validation with recommendations and warnings.');
                }
            } else {
                $io->success('The configuration file passed the validation.');
            }

            return $hasRecommendationsOrWarnings || $input->getOption(self::IGNORE_MESSAGES_OPTION) ? 0 : 1;
        } catch (Exception $exception) {
            // Continue
        }

        if ($io->isVerbose()) {
            throw new RuntimeException(
                sprintf(
                    'The configuration file failed validation: %s',
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }

        if ($exception instanceof JsonValidationException) {
            $io->writeln(
                sprintf(
                    '<error>The configuration file failed validation: "%s" does not match the expected JSON '
                    .'schema:</error>',
                    $exception->getValidatedFile()
                )
            );

            $io->writeln('');

            foreach ($exception->getErrors() as $error) {
                $io->writeln("<comment>  - $error</comment>");
            }
        } else {
            $errorMessage = isset($exception)
                ? sprintf('The configuration file failed validation: %s', $exception->getMessage())
                : 'The configuration file failed validation.'
            ;

            $io->writeln(
                sprintf(
                    '<error>%s</error>',
                    $errorMessage
                )
            );
        }

        return 1;
    }
}
