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

namespace KevinGH\Box\Command\Key;

use KevinGH\Box\Helper\PhpSecLibHelper;
use phpseclib\Crypt\RSA;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a private key.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Create extends Command
{
    /**
     * @override
     */
    protected function configure(): void
    {
        $this->setName('key:create');
        $this->setDescription('Creates a private key');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> command will generate a new PKCS#1 encoded RSA private key.
<comment>
  You may generate a private key without OpenSSL. However,
  it may be useless as you will not be able to sign any
  Phars without the OpenSSL extension enabled. In order to
  accelerate key generation, you may enable any of the
  following extensions: <info>openssl, mcrypt, gmp, bcmath</info>
</comment>
The <info>--bits|-b</info> option allows you to specify key length.
It is recommended that a minimum of 2048 bits be used:

  <comment>http://www.openssl.org/docs/HOWTO/keys.txt</comment>

The <info>--out|-o</info> option allows you to specify the private key file
to store the new PKCS#1 encoded RSA private key.

The <info>--prompt|-p</info> option will cause the command to prompt you
for a passphrase for the new private key. If this option is
not used, no passphrase will be used for the private key.

The <info>--public</info> option allows you to specify the public key file
to create when the private key is generated. Otherwise you may
need to use the <info>key:extract</info> command to extract the public key
at a later time.
HELP
        );
        $this->addOption(
            'bits',
            'b',
            InputOption::VALUE_REQUIRED,
            'The number of bits to generate.',
            2048
        );
        $this->addOption(
            'out',
            'o',
            InputOption::VALUE_REQUIRED,
            'The output file.',
            'private.key'
        );
        $this->addOption(
            'public',
            null,
            InputOption::VALUE_REQUIRED,
            'The public key output file.'
        );
        $this->addOption(
            'prompt',
            'p',
            InputOption::VALUE_NONE,
            'Prompt for a passphrase.'
        );
    }

    /**
     * @override
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        /** @var $lib PhpSecLibHelper */
        $lib = $this->getHelper('phpseclib');
        $rsa = $lib->cryptRSA();
        $verbose = (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity());

        if ($verbose) {
            $output->writeln(
                sprintf(
                    'Generating %d bit private key...',
                    $input->getOption('bits')
                )
            );
        }

        if ($input->getOption('prompt')) {
            /** @var $dialog QuestionHelper */
            $dialog = $this->getHelper('question');

            $rsa->setPassword(
                $dialog->askHiddenResponse($output, 'Private key passphrase: ')
            );
        }

        $rsa->setPrivateKeyFormat(RSA::PRIVATE_FORMAT_PKCS1);
        $rsa->setPublicKeyFormat(RSA::PUBLIC_FORMAT_PKCS1);

        $key = $rsa->createKey($input->getOption('bits'));

        if ($verbose) {
            $output->writeln('Writing private key...');
        }

        file_put_contents($input->getOption('out'), $key['privatekey']);

        if (null !== ($public = $input->getOption('public'))) {
            if ($verbose) {
                $output->writeln('Writing public key...');
            }

            file_put_contents($public, $key['publickey']);
        }
    }
}
