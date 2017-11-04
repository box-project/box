<?php

namespace KevinGH\Box\Command;

use Exception;
use Herrera\Box\Signature;
use Phar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Verifies the Phar signature.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Verify extends Command
{
    /**
     * @override
     */
    protected function configure()
    {
        $this->setName('verify');
        $this->setDescription('Verifies the Phar signature.');
        $this->setHelp(
            <<<HELP
The <info>%command.name%</info> command will verify the signature of the Phar.

By default, the command will use the <comment>phar</comment> extension to perform the
verification process. However, if the extension is not available,
Box will manually extract and verify the phar's signature. If you
require that Box handle the verification process, you will need
to use the <comment>--no-extension</comment> option.

<question>Why would I require that box handle the verification process?</question>

If you meet all of the following conditions:
 - the <comment>phar</comment> extension installed
 - the <comment>openssl</comment> extension is not installed
 - you need to verify a phar signed using a private key

Box supports verifying private key signed phars without using
either extension. <error>Note however, that the entire phar will need
to be read into memory before the verification can be performed.</error>
The library used for handling the verification process does not
currently support progressive/incremental hashing.
HELP
        );
        $this->addArgument(
            'phar',
            InputArgument::REQUIRED,
            'The Phar file.'
        );
        $this->addOption(
            'no-extension',
            null,
            InputOption::VALUE_NONE,
            'Do not use the phar extension to verify.'
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
            $output->writeln('Verifying the Phar...');
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

        $signature = null;
        $verified = false;

        try {
            if (!$input->getOption('no-extension') && extension_loaded('phar')) {
                $phar = new Phar($phar);
                $verified = true;
                $signature = $phar->getSignature();
            } else {
                $phar = new Signature($phar);
                $verified = $phar->verify();
                $signature = $phar->get();
            }
        } catch (Exception $exception) {
        }

        if ($verified) {
            $output->writeln('<info>The Phar passed verification.</info>');

            if ($verbose) {
                $output->writeln($signature['hash_type'] . ' Signature:');
                $output->writeln($signature['hash']);
            }

            return 0;
        }

        $output->writeln('<error>The Phar failed verification.</error>');

        if ($verbose && isset($exception)) {
            throw $exception;
        }

        return 1;
    }
}
