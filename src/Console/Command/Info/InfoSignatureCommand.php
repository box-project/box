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

namespace KevinGH\Box\Console\Command\Info;

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
final class InfoSignatureCommand implements Command
{
    private const PHAR_ARG = 'phar';

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'info:signature',
            'Displays the hash of the signature',
            <<<'HELP'
                The <info>%command.name%</info> command will display the hash of the signature. This may
                be useful for some external tools that wishes to act base on the signature, e.g. to invalidate
                some cache or compare signatures.
                HELP,
            [
                new InputArgument(
                    self::PHAR_ARG,
                    InputArgument::REQUIRED,
                    'The PHAR file.',
                ),
            ],
        );
    }

    public function execute(IO $io): int
    {
        $file = $io->getTypedArgument(self::PHAR_ARG)->asNonEmptyString();

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

        $io->writeln($signature['hash']);

        return ExitCode::SUCCESS;
    }
}
