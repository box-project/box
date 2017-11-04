<?php

namespace KevinGH\Box\Command;

use Phar;
use Phine\Path\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Removes files from a Phar.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Remove extends Command
{
    /**
     * @override
     */
    protected function configure()
    {
        $this->setName('remove');
        $this->setDescription('Removes files from a Phar.');
        $this->setHelp(
            <<<HELP
The <info>%command.name%</info> command will remove one or more files from an
existing Phar, listed as a <info>file</info> argument.
HELP
        );
        $this->addArgument(
            'phar',
            InputArgument::REQUIRED,
            'The Phar to remove from.'
        );
        $this->addArgument(
            'file',
            InputArgument::IS_ARRAY | InputArgument::REQUIRED,
            'The local file path to remove.'
        );
    }

    /**
     * @override
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verbose = (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity());
        $phar = $input->getArgument('phar');

        if ($verbose) {
            $output->writeln('Removing files from the Phar...');
        }

        if (false === is_file($phar)) {
            $output->writeln(
                sprintf(
                    '<error>The path "%s" is not a file or does not exist.</error>',
                    $phar
                )
            );

            return 1;
        }

        $phar = new Phar($phar);

        foreach ((array) $input->getArgument('file') as $file) {
            if (isset($phar[$file])) {
                $phar->delete(str_replace('\\', '/', Path::canonical($file)));
            }
        }

        unset($phar);

        if ($verbose) {
            $output->writeln('Done.');
        }

        return 0;
    }
}
