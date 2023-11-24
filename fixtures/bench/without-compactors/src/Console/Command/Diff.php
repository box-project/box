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

namespace BenchTest\Console\Command;

use BenchTest\Console\PharInfoRenderer;
use BenchTest\Phar\DiffMode;
use BenchTest\Phar\PharDiff;
use BenchTest\Phar\PharInfo;
use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Path;
use ValueError;
use Webmozart\Assert\Assert;
use function array_map;
use function explode;
use function implode;
use function sprintf;
use function str_starts_with;

/**
 * @private
 */
final class Diff implements Command
{
    private const FIRST_PHAR_ARG = 'pharA';
    private const SECOND_PHAR_ARG = 'pharB';

    private const LIST_FILES_DIFF_OPTION = 'list-diff';
    private const GIT_DIFF_OPTION = 'git-diff';
    private const GNU_DIFF_OPTION = 'gnu-diff';
    private const DIFF_OPTION = 'diff';
    private const CHECK_OPTION = 'check';
    private const CHECKSUM_ALGORITHM_OPTION = 'checksum-algorithm';

    private const DEFAULT_CHECKSUM_ALGO = 'sha384';

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'diff',
            '🕵  Displays the differences between all of the files in two PHARs',
            '',
            [
                new InputArgument(
                    self::FIRST_PHAR_ARG,
                    InputArgument::REQUIRED,
                    'The first PHAR',
                ),
                new InputArgument(
                    self::SECOND_PHAR_ARG,
                    InputArgument::REQUIRED,
                    'The second PHAR',
                ),
            ],
            [
                new InputOption(
                    self::GNU_DIFF_OPTION,
                    null,
                    InputOption::VALUE_NONE,
                    '(deprecated) Displays a GNU diff',
                ),
                new InputOption(
                    self::GIT_DIFF_OPTION,
                    null,
                    InputOption::VALUE_NONE,
                    '(deprecated) Displays a Git diff',
                ),
                new InputOption(
                    self::LIST_FILES_DIFF_OPTION,
                    null,
                    InputOption::VALUE_NONE,
                    '(deprecated) Displays a list of file names diff (default)',
                ),
                new InputOption(
                    self::DIFF_OPTION,
                    null,
                    InputOption::VALUE_REQUIRED,
                    sprintf(
                        'Displays a diff of the files. Available options are: "%s"',
                        implode(
                            '", "',
                            DiffMode::values(),
                        ),
                    ),
                    DiffMode::CHECKSUM->value,
                ),
                new InputOption(
                    self::CHECK_OPTION,
                    'c',
                    InputOption::VALUE_OPTIONAL,
                    '(deprecated) Verify the authenticity of the contents between the two PHARs with the given hash function',
                ),
                new InputOption(
                    self::CHECKSUM_ALGORITHM_OPTION,
                    null,
                    InputOption::VALUE_REQUIRED,
                    sprintf(
                        'The hash function used to compare files with the diff mode used is "%s".',
                        DiffMode::CHECKSUM->value,
                    ),
                    self::DEFAULT_CHECKSUM_ALGO,
                ),
            ],
        );
    }

    public function execute(IO $io): int
    {
        $diff = new PharDiff(...self::getPaths($io));
        $diffMode = self::getDiffMode($io);
        $checksumAlgorithm = self::getChecksumAlgorithm($io);

        $io->comment('<info>Comparing the two archives...</info>');

        if ($diff->equals()) {
            $io->success('The two archives are identical.');

            return ExitCode::SUCCESS;
        }

        self::renderSummary($diff->getPharInfoA(), $io);
        $io->newLine();
        self::renderSummary($diff->getPharInfoB(), $io);

        $this->renderArchivesDiff($diff, $io);
        $this->renderContentsDiff($diff, $diffMode, $checksumAlgorithm, $io);

        return ExitCode::FAILURE;
    }

    /**
     * @return array{non-empty-string, non-empty-string}
     */
    private static function getPaths(IO $io): array
    {
        $paths = [
            $io->getTypedArgument(self::FIRST_PHAR_ARG)->asNonEmptyString(),
            $io->getTypedArgument(self::SECOND_PHAR_ARG)->asNonEmptyString(),
        ];

        Assert::allFile($paths);

        return array_map(
            static fn (string $path) => Path::canonicalize($path),
            $paths,
        );
    }

    private function renderArchivesDiff(PharDiff $diff, IO $io): void
    {
        $pharASummary = self::getShortSummary($diff->getPharInfoA(), $io);
        $pharBSummary = self::getShortSummary($diff->getPharInfoB(), $io);

        if ($pharASummary === $pharBSummary) {
            return;
        }

        $io->writeln(
            self::createColorizedDiff(
                $pharASummary,
                $pharBSummary,
            ),
        );
    }

    private static function createColorizedDiff(string $pharASummary, string $pharBSummary): string
    {
        $differ = new Differ(
            new UnifiedDiffOutputBuilder(
                "\n<diff-expected>--- PHAR A</diff-expected>\n<diff-actual>+++ PHAR B</diff-actual>\n",
            ),
        );

        $result = $differ->diff(
            $pharASummary,
            $pharBSummary,
        );

        $lines = explode("\n", $result);

        $colorizedLines = array_map(
            static fn (string $line) => match (true) {
                str_starts_with($line, '+') => sprintf(
                    '<diff-actual>%s</diff-actual>',
                    $line,
                ),
                str_starts_with($line, '-') => sprintf(
                    '<diff-expected>%s</diff-expected>',
                    $line,
                ),
                default => $line,
            },
            $lines,
        );

        return implode("\n", $colorizedLines);
    }

    private static function getDiffMode(IO $io): DiffMode
    {
        if ($io->getTypedOption(self::GNU_DIFF_OPTION)->asBoolean()) {
            $io->writeln(
                sprintf(
                    '⚠️  <warning>Using the option "%s" is deprecated. Use "--%s=%s" instead.</warning>',
                    self::GNU_DIFF_OPTION,
                    self::DIFF_OPTION,
                    DiffMode::GNU->value,
                ),
            );

            return DiffMode::GNU;
        }

        if ($io->getTypedOption(self::GIT_DIFF_OPTION)->asBoolean()) {
            $io->writeln(
                sprintf(
                    '⚠️  <warning>Using the option "%s" is deprecated. Use "--%s=%s" instead.</warning>',
                    self::GIT_DIFF_OPTION,
                    self::DIFF_OPTION,
                    DiffMode::GIT->value,
                ),
            );

            return DiffMode::GIT;
        }

        if ($io->getTypedOption(self::LIST_FILES_DIFF_OPTION)->asBoolean()) {
            $io->writeln(
                sprintf(
                    '⚠️  <warning>Using the option "%s" is deprecated. Use "--%s=%s" instead.</warning>',
                    self::LIST_FILES_DIFF_OPTION,
                    self::DIFF_OPTION,
                    DiffMode::FILE_NAME->value,
                ),
            );

            return DiffMode::FILE_NAME;
        }

        if ($io->hasOption('-c') || $io->hasOption('--check')) {
            $io->writeln(
                sprintf(
                    '⚠️  <warning>Using the option "%s" is deprecated. Use "--%s=%s" instead.</warning>',
                    self::CHECK_OPTION,
                    self::DIFF_OPTION,
                    DiffMode::CHECKSUM->value,
                ),
            );

            return DiffMode::FILE_NAME;
        }

        $rawDiffOption = $io->getTypedOption(self::DIFF_OPTION)->asNonEmptyString();

        try {
            return DiffMode::from($rawDiffOption);
        } catch (ValueError) {
            // Rethrow a more user-friendly error message
            throw new RuntimeException(
                sprintf(
                    'Invalid diff mode "%s". Expected one of: "%s".',
                    $rawDiffOption,
                    implode(
                        '", "',
                        DiffMode::values(),
                    ),
                ),
            );
        }
    }

    private static function getChecksumAlgorithm(IO $io): string
    {
        $checksumAlgorithm = $io->getTypedOption(self::CHECK_OPTION)->asNullableNonEmptyString();

        if (null !== $checksumAlgorithm) {
            $io->writeln(
                sprintf(
                    '⚠️  <warning>Using the option "%s" is deprecated. Use "--%s=\<algorithm\>" instead.</warning>',
                    self::CHECK_OPTION,
                    self::CHECKSUM_ALGORITHM_OPTION,
                ),
            );

            return $checksumAlgorithm;
        }

        return $io->getTypedOption(self::CHECKSUM_ALGORITHM_OPTION)->asNullableNonEmptyString() ?? self::DEFAULT_CHECKSUM_ALGO;
    }

    private function renderContentsDiff(PharDiff $diff, DiffMode $diffMode, string $checksumAlgorithm, IO $io): void
    {
        $io->comment(
            sprintf(
                '<info>Comparing the two archives contents (%s diff)...</info>',
                $diffMode->value,
            ),
        );

        $diff->diff($diffMode, $checksumAlgorithm, $io);
    }

    private static function renderSummary(PharInfo $pharInfo, IO $io): void
    {
        $io->writeln(
            sprintf(
                '<comment>Archive: </comment><fg=cyan;options=bold>%s</>',
                $pharInfo->getFileName(),
            ),
        );

        PharInfoRenderer::renderShortSummary($pharInfo, $io);
    }

    private static function getShortSummary(PharInfo $pharInfo, IO $io): string
    {
        $output = new BufferedOutput(
            $io->getVerbosity(),
            false,
            clone $io->getOutput()->getFormatter(),
        );

        PharInfoRenderer::renderShortSummary(
            $pharInfo,
            $io->withOutput($output),
        );

        return $output->fetch();
    }
}
