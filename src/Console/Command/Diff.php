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
use KevinGH\Box\PhpSettingsHandler;
use ParagonIE\Pharaoh\Pharaoh;
use ParagonIE\Pharaoh\PharDiff;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use function array_map;
use function realpath;
use function sprintf;

/**
 * @private
 */
final class Diff extends Command
{
    use CreateTemporaryPharFile;

    private const FIRST_PHAR_ARG = 'pharA';
    private const SECOND_PHAR_ARG = 'pharB';

    private const GNU_DIFF_OPTION = 'gnu-diff';
    private const CHECK_OPTION = 'check';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('diff');
        $this->setDescription('🕵  Displays the differences between all of the files in two PHARs');

        $this->addArgument(
            self::FIRST_PHAR_ARG,
            InputArgument::REQUIRED,
            'The first PHAR'
        );
        $this->addArgument(
            self::SECOND_PHAR_ARG,
            InputArgument::REQUIRED,
            'The second PHAR'
        );

        $this->addOption(
            self::GNU_DIFF_OPTION,
            'd',
            InputOption::VALUE_NONE,
            'Displays a GNU diff instead of the default git diff'
        );
        $this->addOption(
            self::CHECK_OPTION,
            'c',
            InputOption::VALUE_OPTIONAL,
            'Verify the authenticity of the contents between the two PHARs with the given hash function.',
            'sha384'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        (new PhpSettingsHandler(new ConsoleLogger($output)))->check();

        $paths = [
            $input->getArgument(self::FIRST_PHAR_ARG),
            $input->getArgument(self::SECOND_PHAR_ARG),
        ];

        Assertion::allFile($paths);

        try {
            $diff = new PharDiff(
                ...array_map(
                    static function (string $path): Pharaoh {
                        $realPath = realpath($path);

                        return new Pharaoh(false !== $realPath ? $realPath : $path);
                    },
                    $paths
                )
            );
            $diff->setVerbose(true);
        } catch (Throwable $throwable) {
            if ($output->isDebug()) {
                throw $throwable;
            }

            $io->writeln(
                sprintf(
                    '<error>Could not check the PHARs: %s</error>',
                    $throwable->getMessage()
                )
            );

            return 1;
        }

        if ($input->hasParameterOption(['-c', '--check'])) {
            return $diff->listChecksums($input->getOption(self::CHECK_OPTION) ?? 'sha384');
        }

        if ($input->getOption(self::GNU_DIFF_OPTION)) {
            return $diff->printGnuDiff();
        }

        return $diff->printGitDiff();
    }
}
