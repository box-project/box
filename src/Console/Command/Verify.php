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

use Assert\Assertion;
use Phar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

final class Verify extends Command
{
    private const PHAR_ARG = 'phar';
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

<question>Why would I require that box handle the verification process?</question>

If you meet all of the following conditions:
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
            $phar = new Phar($pharPath);

            $verified = true;
            $signature = $phar->getSignature();
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
