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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use function KevinGH\Box\FileSystem\remove;
use function realpath;
use function sprintf;

/**
 * @private
 */
final class Verify extends Command
{
    use CreateTemporaryPharFile;

    private const PHAR_ARG = 'phar';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('verify');
        $this->setDescription('🔐️  Verifies the PHAR signature');
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

        $io->newLine();
        $io->writeln(
            sprintf(
                '🔐️  Verifying the PHAR "<comment>%s</comment>"',
                $pharPath
            )
        );
        $io->newLine();

        $tmpPharPath = $this->createTemporaryPhar($pharPath);

        $verified = false;
        $signature = null;
        $throwable = null;

        try {
            $phar = new Phar($tmpPharPath);

            $verified = true;
            $signature = $phar->getSignature();
        } catch (Throwable $throwable) {
            // Continue
        } finally {
            if ($tmpPharPath !== $pharPath) {
                remove($tmpPharPath);
            }
        }

        if (false === $verified || null === $signature) {
            $message = null !== $throwable && '' !== $throwable->getMessage()
                ? $throwable->getMessage()
                : 'Unknown reason.'
            ;

            $io->writeln(
                sprintf(
                    '<error>The PHAR failed the verification: %s</error>',
                    $message
                )
            );

            if (null !== $throwable && $output->isDebug()) {
                throw $throwable;
            }

            return 1;
        }

        $io->writeln('<info>The PHAR passed verification.</info>');

        $io->newLine();
        $io->writeln(
            sprintf(
                '%s signature: <info>%s</info>',
                $signature['hash_type'],
                $signature['hash']
            )
        );

        return 0;
    }
}
