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

namespace KevinGH\Box\Command;

use Phar;
use Phine\Path\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extracts files from a Phar.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Extract extends Command
{
    /**
     * @override
     */
    protected function configure(): void
    {
        $this->setName('extract');
        $this->setDescription('Extracts files from a Phar.');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> will extract one or more files from a Phar.

If the <info>--pick|-p</info> option is used, only the file or directories requested
will be extracted from the Phar. If the option is not used, the entire
contents of the Phar will be extracted.

The <info>output</info> specifies the directory where the extracted contents should
be placed. By default the directory will be called <comment>name.phar-</comment>contents,
where <comment>name.phar</comment> is the name of the Phar file.
HELP
        );
        $this->addArgument(
            'phar',
            InputArgument::REQUIRED,
            'The Phar to extract from.'
        );
        $this->addOption(
            'pick',
            'p',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'The file or directory to cherry pick.'
        );
        $this->addOption(
            'out',
            'o',
            InputOption::VALUE_REQUIRED,
            'The alternative output directory. (default: name.phar-contents)'
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
            $output->writeln('Extracting files from the Phar...');
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

        if (null === ($out = $input->getOption('out'))) {
            $out = $phar.'-contents';
        }

        $phar = new Phar($phar);
        $files = $input->getOption('pick') ?: null;

        // backslash paths causes segfault
//        if ($files) {
//            array_walk(
//                $files,
//                function (&$file) {
//                    $file = str_replace(
//                        '\\',
//                        '/',
//                        Path::canonical($file)
//                    );
//                }
//            );
//        }

        $phar->extractTo($out, $files, true);

        unset($phar);

        if ($verbose) {
            $output->writeln('Done.');
        }

        return 0;
    }
}
