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

namespace KevinGH\Box\Console\Command\Generate;

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use KevinGH\Box\Phar\PharInfo;
use KevinGH\Box\RequirementChecker\SuccinctRequirementListFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Path;

/**
 * @private
 */
final class Requirements implements Command
{
    private const PHAR_ARG = 'phar';

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'generate:requirements',
            'Outputs a succinct list of the PHAR requirements',
            <<<'HELP'
                The <info>%command.name%</info> command will generate a succinct list of the requirements of the PHAR
                if it ships the Box's RequirementChecker.

                This command is mostly to generate a list to be able to use it with <info>check:requirements</info> to
                keep track of the PHAR requirements.

                If what you want to do is check the more detailed list of the requirements of your PHAR use the
                <info>info</info>> command instead.
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
        $pharPath = $io->getTypedArgument(self::PHAR_ARG)->asNonEmptyString();

        $pharPath = Path::canonicalize($pharPath);

        $pharInfo = new PharInfo($pharPath);
        $requirements = $pharInfo->getRequirements();

        $io->writeln(SuccinctRequirementListFactory::create($requirements));

        return ExitCode::SUCCESS;
    }
}
