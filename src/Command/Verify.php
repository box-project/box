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

use Assert\Assertion;
use KevinGH\Box\Signature;
use Phar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

final class Verify extends Command
{
    private const PHAR_ARG = 'phar';
    private const NO_EXTENSION_OPT = 'no-extension';
    private const VERBOSITY_LEVEL = OutputInterface::VERBOSITY_VERBOSE;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('verify');
        $this->setDescription('Verifies the PHAR signature');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> command will verify the signature of the PHAR.

By default, the command will use the <comment>phar</comment> extension to perform the
verification process. However, if the extension is not available, Box will manually
extract and verify the PHAR's signature. If you require that Box handle the verification
process, you will need to use the <comment>--no-extension</comment> option.

<question>Why would I require that box handle the verification process?</question>

If you meet all of the following conditions:
 - The <comment>phar</comment> extension installed
 - The <comment>openssl</comment> extension is not installed
 - You need to verify a PHAR signed using a private key

Box supports verifying private key signed PHARs without using
either extensions. <error>Note however, that the entire PHAR will need
to be read into memory before the verification can be performed.</error>
HELP
        );
        $this->addArgument(
            self::PHAR_ARG,
            InputArgument::REQUIRED,
            'The PHAR file'
        );
        $this->addOption(
            self::NO_EXTENSION_OPT,
            null,
            InputOption::VALUE_NONE,
            'Do not use the PHAR extension to verify'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pharPath = $input->getArgument(self::PHAR_ARG);

        Assertion::file($pharPath);

        $pharPath = false !== realpath($pharPath) ? realpath($pharPath) : $pharPath;

        $io->writeln(
            sprintf(
                'Verifying the PHAR "<comment>%s</comment>"...',
                $pharPath
            ),
            self::VERBOSITY_LEVEL
        );

        try {
            if (!$input->getOption(self::NO_EXTENSION_OPT) && extension_loaded('phar')) {
                $phar = new Phar($pharPath);

                $verified = true;
                $signature = $phar->getSignature();
            } else {
                $phar = new Signature($pharPath);

                $verified = $phar->verify();
                $signature = $phar->get();
            }
        } catch (Throwable $throwable) {
            // Continue

            $verified = false;
            $signature = null;
        }

        if (false === $verified) {
            $message = isset($throwable) && '' !== $throwable->getMessage()
                ? $throwable->getMessage()
                : 'Unknown reason.'
            ;

            $io->writeln(
                sprintf(
                    '<error>The PHAR failed the verification: %s</error>',
                    $message
                )
            );

            if ($output->isVerbose() && isset($throwable)) {
                throw $throwable;
            }

            return 1;
        }

        $io->writeln('<info>The PHAR passed verification.</info>');

        $io->writeln(
            '',
            self::VERBOSITY_LEVEL
        );
        $io->writeln(
            sprintf(
                '%s signature: <info>%s</info>',
                $signature['hash_type'],
                $signature['hash']
            ),
            self::VERBOSITY_LEVEL
        );

        return 0;
    }
}
