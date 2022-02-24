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

use function file_exists;
use KevinGH\Box\Console\IO\IO;
use function KevinGH\Box\create_temporary_phar;
use function KevinGH\Box\FileSystem\copy;
use function KevinGH\Box\FileSystem\remove;
use Phar;
use function realpath;
use function sprintf;
use Symfony\Component\Console\Input\InputArgument;
use Throwable;
use Webmozart\Assert\Assert;

/**
 * @private
 */
final class Verify extends BaseCommand
{
    private const PHAR_ARG = 'phar';

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

    protected function executeCommand(IO $io): int
    {
        /** @var string $pharPath */
        $pharPath = $io->getInput()->getArgument(self::PHAR_ARG);

        Assert::file($pharPath);

        $pharPath = false !== realpath($pharPath) ? realpath($pharPath) : $pharPath;

        $io->newLine();
        $io->writeln(
            sprintf(
                '🔐️  Verifying the PHAR "<comment>%s</comment>"',
                $pharPath
            )
        );
        $io->newLine();

        $tmpPharPath = create_temporary_phar($pharPath);

        if (file_exists($pharPubKey = $pharPath.'.pubkey')) {
            copy($pharPubKey, $tmpPharPath.'.pubkey');
        }

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
            remove($tmpPharPath);
        }

        if (false === $verified || null === $signature) {
            return $this->failVerification($throwable, $io);
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

    private function failVerification(?Throwable $throwable, IO $io): int
    {
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

        if (null !== $throwable && $io->isDebug()) {
            throw $throwable;
        }

        return 1;
    }
}
