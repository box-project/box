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

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use KevinGH\Box\Phar\PharInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Path;
use Throwable;
use Webmozart\Assert\Assert;
use function realpath;
use function sprintf;

/**
 * @private
 */
final class VerifyCommand implements Command
{
    private const PHAR_ARG = 'phar';

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'verify',
            '🔐️  Verifies the PHAR signature',
            <<<'HELP'
                The <info>%command.name%</info> command will verify the signature of the PHAR.

                <question>Why would I require that box handle the verification process?</question>

                If you meet all the following conditions:
                 - The <comment>openssl</comment> extension is not installed
                 - You need to verify a PHAR signed using a private key

                Box supports verifying private key signed PHARs without using
                either extensions. <error>Note however, that the entire PHAR will need
                to be read into memory before the verification can be performed.</error>
                HELP,
            [
                new InputArgument(
                    self::PHAR_ARG,
                    InputArgument::REQUIRED,
                    'The PHAR file',
                ),
            ],
        );
    }

    public function execute(IO $io): int
    {
        $pharFilePath = self::getPharFilePath($io);

        $io->newLine();
        $io->writeln(
            sprintf(
                '🔐️  Verifying the PHAR "<comment>%s</comment>"',
                $pharFilePath,
            ),
        );
        $io->newLine();

        [$verified, $signature, $throwable] = self::verifyPhar($pharFilePath);

        if (false === $verified || false === $signature) {
            return self::failVerification($throwable, $io);
        }

        $io->writeln('<info>The PHAR passed verification.</info>');

        $io->newLine();
        $io->writeln(
            sprintf(
                '%s signature: <info>%s</info>',
                $signature['hash_type'],
                $signature['hash'],
            ),
        );

        return ExitCode::SUCCESS;
    }

    private static function getPharFilePath(IO $io): string
    {
        $pharPath = Path::canonicalize(
            $io->getTypedArgument(self::PHAR_ARG)->asNonEmptyString(),
        );

        Assert::file($pharPath);

        $pharRealPath = realpath($pharPath);

        return false === $pharRealPath ? $pharPath : $pharRealPath;
    }

    /**
     * @return array{bool, array{hash: string, hash_type:string}|false, Throwable|null}
     */
    private static function verifyPhar(string $pharFilePath): array
    {
        $verified = false;
        $signature = false;
        $throwable = null;

        try {
            $pharInfo = new PharInfo($pharFilePath);

            $verified = true;
            $signature = $pharInfo->getSignature();
        } catch (Throwable $throwable) {
            // Continue
        }

        return [
            $verified,
            $signature,
            $throwable,
        ];
    }

    private static function failVerification(?Throwable $throwable, IO $io): int
    {
        $message = null !== $throwable && '' !== $throwable->getMessage()
            ? $throwable->getMessage()
            : 'Unknown reason.';

        $io->writeln(
            sprintf(
                '<error>The PHAR failed the verification: %s</error>',
                $message,
            ),
        );

        if (null !== $throwable && $io->isDebug()) {
            throw $throwable;
        }

        return ExitCode::FAILURE;
    }
}
