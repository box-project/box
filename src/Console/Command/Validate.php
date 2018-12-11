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
use KevinGH\Box\Console\MessageRenderer;
use KevinGH\Box\Console\OutputConfigurator;
use KevinGH\Box\Json\JsonValidationException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function sprintf;

/**
 * @private
 */
final class Validate extends Command
{
    private const FILE_ARGUMENT = 'file';
    private const IGNORE_MESSAGES_OPTION = 'ignore-recommendations-and-warnings';

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        OutputConfigurator::configure($output);

        try {
            $config = ConfigurationLoader::getConfig(
                $input->getArgument(self::FILE_ARGUMENT) ?? $this->getConfigurationHelper()->findDefaultPath(),
                $this->getConfigurationHelper(),
                new SymfonyStyle($input, $output),
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

        if ($output->isVerbose()) {
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
            $output->writeln(
                sprintf(
                    '<error>The configuration file failed validation: "%s" does not match the expected JSON '
                    .'schema:</error>',
                    $exception->getValidatedFile()
                )
            );

            $output->writeln('');

            foreach ($exception->getErrors() as $error) {
                $output->writeln("<comment>  - $error</comment>");
            }
        } else {
            $errorMessage = isset($exception)
                ? sprintf('The configuration file failed validation: %s', $exception->getMessage())
                : 'The configuration file failed validation.'
            ;

            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    $errorMessage
                )
            );
        }

        return 1;
    }
}
