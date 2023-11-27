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
use Fidry\FileSystem\FS;
use KevinGH\Box\Phar\PharInfo;
use KevinGH\Box\RequirementChecker\SuccinctRequirementListFactory;
use SebastianBergmann\Diff\Diff;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Path;
use function implode;
use function sprintf;
use function trim;

/**
 * @private
 */
final class Requirements implements Command
{
    private const PHAR_ARG = 'phar';
    private const EXPECTED_REQUIREMENTS_ARG = 'expected-requirements';

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'check:requirements',
            'Displays the hash of the signature',
            <<<'HELP'
                The <info>%command.name%</info> command will check that the requirements of the provided PHAR
                matches the list of provided requirements.
                The purpose of this command is to check that the PHAR ships the requirement checker and to keep
                track of the extensions required.
                
                If what you want to do is check if the current environment satisfies the PHAR
                requirements simply execute the PHAR instead.

                The requirements should be listed in the file as follows:
                
                ```
                PHP ^7.2
                ext-phar
                ext-xml
                ext-filter
                ```
                HELP,
            [
                new InputArgument(
                    self::PHAR_ARG,
                    InputArgument::REQUIRED,
                    'The PHAR file.',
                ),
                new InputArgument(
                    self::EXPECTED_REQUIREMENTS_ARG,
                    InputArgument::REQUIRED,
                    'Path to a file containing a line return separated list of requirements.',
                ),
            ],
        );
    }

    public function execute(IO $io): int
    {
        $pharPath = $io->getTypedArgument(self::PHAR_ARG)->asNonEmptyString();
        $expectedRequirementPath = $io->getTypedArgument(self::EXPECTED_REQUIREMENTS_ARG)->asNonEmptyString();

        $pharPath = Path::canonicalize($pharPath);

        $pharInfo = new PharInfo($pharPath);

        $actualRequirements = trim(
            implode(
                "\n",
                SuccinctRequirementListFactory::create($pharInfo->getRequirements()),
            ),
        );
        $expectedRequirements = trim(
            FS::getFileContents($expectedRequirementPath),
        );

        if ($expectedRequirements === $actualRequirements) {
            return ExitCode::SUCCESS;
        }

        $differ = new Differ(new UnifiedDiffOutputBuilder());
        $result = $differ->diff($expectedRequirements, $actualRequirements);

        $io->writeln($result);

        return ExitCode::FAILURE;
    }
}
