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

namespace KevinGH\Box\Console\Command\Check;

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use KevinGH\Box\Phar\PharInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Path;
use function sprintf;

/**
 * @private
 */
final class CheckSignatureCommand implements Command
{
    private const PHAR_ARG = 'phar';
    private const HASH = 'hash';

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'check:signature',
            'Checks the hash of the signature',
            <<<'HELP'
                The <info>%command.name%</info> command will check that the hash of the signature is the
                one given.
                HELP,
            [
                new InputArgument(
                    self::PHAR_ARG,
                    InputArgument::REQUIRED,
                    'The PHAR file.',
                ),
                new InputArgument(
                    self::HASH,
                    InputArgument::REQUIRED,
                    'The expected signature hash.',
                ),
            ],
        );
    }

    public function execute(IO $io): int
    {
        $file = $io->getTypedArgument(self::PHAR_ARG)->asNonEmptyString();
        $expectedHash = $io->getTypedArgument(self::HASH)->asNonEmptyString();

        $file = Path::canonicalize($file);

        $pharInfo = new PharInfo($file);
        $signature = $pharInfo->getSignature();

        if (null === $signature) {
            $io->error(
                sprintf(
                    'The file "%s" is not a PHAR.',
                    $file,
                ),
            );

            return ExitCode::FAILURE;
        }

        $actualHash = $signature['hash'];

        if ($expectedHash === $actualHash) {
            return ExitCode::SUCCESS;
        }

        $io->error(
            sprintf(
                'Found the hash "%s".',
                $actualHash,
            ),
        );

        return ExitCode::FAILURE;
    }
}
