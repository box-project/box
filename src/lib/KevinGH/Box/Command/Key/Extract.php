<?php

namespace KevinGH\Box\Command\Key;

use KevinGH\Box\Helper\PhpSecLibHelper;
use phpseclib\Crypt\RSA;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extracts the public key.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Extract extends Command
{
    /**
     * @override
     */
    protected function configure()
    {
        $this->setName('key:extract');
        $this->setDescription('Extracts the public key from a private key.');
        $this->setHelp(
            <<<HELP
The <info>php %command.name%</info> command will extract the public key from an existing
private key file. <comment>You may need to generate a new private key using
<info>key:create</info>.</comment>
<comment>
  You may extract a public key without OpenSSL. However,
  it may be useless as you will not be able to sign any
  Phars without the OpenSSL extension enabled. In order to
  accelerate key extraction, you may enable any of the
  following extensions: <info>openssl, mcrypt, gmp, bcmath</info>
</comment>
The <info>--private</info> argument allows you to specifiy the name of the private key
file. <comment>The default file name for <info>key:create</info> is <info>private.key</info>.</comment>  The private
key file must contain a PKCS#1 encoded RSA private key.

The <info>--prompt|-p</info> option will cause the command to prompt you for the
passphrase for the private key. If this option is not used, no passphrase
will be used.

The <info>--out|-o</info> option allows you to specify the public key file to store the
PKCS#1 encoded RSA public key.
HELP
        );
        $this->addArgument(
            'private',
            InputArgument::REQUIRED,
            'The private key file.'
        );
        $this->addOption(
            'out',
            'o',
            InputOption::VALUE_REQUIRED,
            'The output file. (default: public.key)',
            'public.key'
        );
        $this->addOption(
            'prompt',
            'p',
            InputOption::VALUE_NONE
        );
    }

    /**
     * @override
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var $lib PhpSecLibHelper */
        $lib = $this->getHelper('phpseclib');
        $rsa = $lib->cryptRSA();
        $verbose = (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity());

        if ($verbose) {
            $output->writeln('Extracting public key...');
        }

        if ($input->getOption('prompt')) {
            /** @var $dialog QuestionHelper */
            $dialog = $this->getHelper('question');

            $rsa->setPassword(
                $dialog->askHiddenResponse($output, 'Private key passphrase: ')
            );
        }

        $result = $rsa->loadKey(
            file_get_contents($input->getArgument('private')),
            RSA::PRIVATE_FORMAT_PKCS1
        );

        if (false === $result) {
            $output->writeln(
                '<error>The private key could not be parsed.</error>'
            );

            return 1;
        }

        $rsa->setPublicKey();

        if (false === ($public = $rsa->getPublicKey())) {
            $output->writeln(
                '<error>The public key could not be retrieved.</error>'
            );

            return 1;
        }

        if ($verbose) {
            $output->writeln('Writing public key...');
        }

        file_put_contents($input->getOption('out'), $public);

        return 0;
    }
}
