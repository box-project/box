<?php

namespace KevinGH\Box\Command;

use Herrera\Box\Box;
use KevinGH\Box\Configuration;
use Phine\Path\Path;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adds files to a Phar.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Add extends Configurable
{
    /**
     * The Box instance.
     *
     * @var Box
     */
    private $box;

    /**
     * The configuration settings.
     *
     * @var Configuration
     */
    private $config;

    /**
     * @override
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('add');
        $this->setDescription('Adds or replaces files to a Phar.');
        $this->setHelp(
            <<<HELP
The <info>%command.name%</info> will add a new file to an existing Phar, or replace
an existing file with a new one.
<comment>
  This command relies on a configuration file for loading
  Phar packaging settings. If a configuration file is not
  specified through the <info>--configuration|-c</info> option, one of
  the following files will be used (in order): <info>box.json,
  box.json.dist</info>
</comment>
If the <info>--binary|-b</info> option is set, the file being added will
be treated as a binary file. This means that will be added
to the Phar as is, without any modifications.

If the <info>--main|-m</info> option is set, the file being added will
be treated as the main file (as defined in the configuration
file: main). The file will have it's #! line removed, be
processed by any configured compactors, and have its
placeholder values replaced before being added to the Phar.

If the <info>--replace|-r</info> option is set, the file in the Phar will be
replaced if it already exists. If this option is not set and it
already exists in the Phar, the command will fail.

If the <info>--stub|-s</info> option is set, the file being added will be
treated as a stub. The new stub will be imported as is into
the Phar, replacing the current stub.
HELP
        );
        $this->addArgument(
            'phar',
            InputArgument::REQUIRED,
            'The Phar file to update.'
        );
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The file to add.'
        );
        $this->addArgument(
            'local',
            InputArgument::OPTIONAL,
            'The local file path inside the Phar.'
        );
        $this->addOption(
            'binary',
            'b',
            InputOption::VALUE_NONE,
            'Treat the file as a binary file.'
        );
        $this->addOption(
            'main',
            'm',
            InputOption::VALUE_NONE,
            'Treat the file as the main script.'
        );
        $this->addOption(
            'replace',
            'r',
            InputOption::VALUE_NONE,
            'Replace the file if it already exists.'
        );
        $this->addOption(
            'stub',
            's',
            InputOption::VALUE_NONE,
            'Treat the file as a stub.'
        );
    }

    /**
     * @override
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ((false === $input->getOption('stub'))
            && (null === $input->getArgument('local'))) {
            $output->writeln(
                '<error>The <info>local</info> argument is required.</error>'
            );

            return 1;
        }

        $this->config = $this->getConfig($input);
        $phar = $input->getArgument('phar');
        $file = $input->getArgument('file');

        // load bootstrap file
        if (null !== ($bootstrap = $this->config->getBootstrapFile())) {
            $this->config->loadBootstrap();
            $this->putln('?', "Loading bootstrap file: $bootstrap");

            unset($bootstrap);
        }

        $this->putln('*', 'Adding to the Phar...');

        if (false === is_file($phar)) {
            $output->writeln(
                sprintf(
                    '<error>The path "%s" is not a file or does not exist.</error>',
                    $phar
                )
            );

            return 1;
        }

        if (false === is_file($file)) {
            $output->writeln(
                sprintf(
                    '<error>The path "%s" is not a file or does not exist.</error>',
                    $file
                )
            );

            return 1;
        }

        if (false == preg_match('/^\w+:\/\//', $file)) {
            $file = realpath($file);
        }

        $this->box = Box::create($phar);

        // set replacement values, if any
        if (array() !== ($values = $this->config->getProcessedReplacements())) {
            $this->putln('?', 'Setting replacement values...');

            if ($this->isVerbose()) {
                foreach ($values as $key => $value) {
                    $this->putln('+', "$key: $value");
                }
            }

            $this->box->setValues($values);

            unset($values, $key, $value);
        }

        // register configured compactors
        if (array() !== ($compactors = $this->config->getCompactors())) {
            $this->putln('?', 'Registering compactors...');

            foreach ($compactors as $compactor) {
                $this->putln('+', get_class($compactor));

                $this->box->addCompactor($compactor);
            }
        }

        // add the file
        $phar = $this->box->getPhar();
        $local = str_replace('\\', '/', Path::canonical($input->getArgument('local')));

        if (isset($phar[$local]) && (false === $input->getOption('replace'))) {
            $output->writeln(
                sprintf(
                    '<error>The file "%s" already exists in the Phar.</error>',
                    $local
                )
            );

            return 1;
        }

        if ($input->getOption('binary')) {
            $this->putln('?', "Adding binary file: $file");

            $phar->addFile($file, $local);
        } elseif ($input->getOption('stub')) {
            $this->putln('?', "Using stub file: $file");

            $this->box->setStubUsingFile($file);
        } elseif ($input->getOption('main')) {
            $this->putln('?', "Adding main file: $file");

            if (false === ($contents = @file_get_contents($file))) {
                $error = error_get_last();

                throw new RuntimeException($error['message']);
            }

            $this->box->addFromString(
                $local,
                preg_replace('/^#!.*\s*/', '', $contents)
            );
        } else {
            $this->putln('?', "Adding file: $file");

            $this->box->addFile($file, $local);
        }

        unset($this->box, $phar);

        $this->putln('*', 'Done.');

        return 0;
    }
}
