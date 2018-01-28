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
use KevinGH\Box\Json\Json;
use KevinGH\Box\Json\JsonValidationException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Validate extends Configurable
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('validate');
        $this->setDescription('Validates the configuration file');
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
            'file',
            InputArgument::OPTIONAL,
            'The configuration file. (default: box.json, box.json.dist)'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->getConfig($input);

            $output->writeln(
                '<info>The configuration file passed validation.</info>'
            );

            return 0;
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
