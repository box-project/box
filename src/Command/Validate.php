<?php

namespace KevinGH\Box\Command;

use Exception;
use Herrera\Json\Exception\JsonException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Validates the configuration file.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Validate extends Configurable
{
    /**
     * @override
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('validate');
        $this->setDescription('Validates the configuration file.');
        $this->setHelp(
            <<<HELP
The <info>%command.name%</info> command will validate the configuration file
and report any errors found, if any.
<comment>
  This command relies on a configuration file for loading
  Phar packaging settings. If a configuration file is not
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
     * @override
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verbose = (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity());

        if ($verbose) {
            $output->writeln('Validating the Box configuration file...');
        }

        try {
            $this->getConfig($input);

            $output->writeln(
                '<info>The configuration file passed validation</info>.'
            );
        } catch (Exception $exception) {
            $output->writeln(
                '<error>The configuration file failed validation.</error>'
            );

            if ($verbose) {
                if ($exception instanceof JsonException) {
                    $output->writeln('');

                    foreach ($exception->getErrors() as $error) {
                        $output->writeln("<comment>  - $error</comment>");
                    }
                } else {
                    throw $exception;
                }
            }

            return 1;
        }

        return 0;
    }
}
