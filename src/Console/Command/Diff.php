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
use Fidry\Console\Input\IO;
use KevinGH\Box\Console\PharInfoRenderer;
use KevinGH\Box\Phar\CompressionAlgorithm;
use KevinGH\Box\Phar\PharDiff;
use KevinGH\Box\Phar\PharInfo;
use PharFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Path;
use Throwable;
use Webmozart\Assert\Assert;
use function array_map;
use function count;
// TODO: migrate to Safe API
use function is_string;
use function KevinGH\Box\check_php_settings;
use function KevinGH\Box\format_size;
use function sprintf;

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
    private const CHECK_OPTION = 'check';

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
                    'Displays a GNU diff',
                ),
                new InputOption(
                    self::GIT_DIFF_OPTION,
                    null,
                    InputOption::VALUE_NONE,
                    'Displays a Git diff',
                ),
                new InputOption(
                    self::LIST_FILES_DIFF_OPTION,
                    null,
                    InputOption::VALUE_NONE,
                    'Displays a list of file names diff (default)',
                ),
                new InputOption(
                    self::CHECK_OPTION,
                    'c',
                    InputOption::VALUE_OPTIONAL,
                    'Verify the authenticity of the contents between the two PHARs with the given hash function',
                    self::DEFAULT_CHECKSUM_ALGO,
                ),
            ],
        );
    }

    public function execute(IO $io): int
    {
        check_php_settings($io);

        $paths = self::getPaths($io);

        try {
            $diff = new PharDiff(...$paths);
        } catch (Throwable $throwable) {
            if ($io->isDebug()) {
                throw $throwable;
            }

            $io->writeln(
                sprintf(
                    '<error>Could not check the PHARs: %s</error>',
                    $throwable->getMessage(),
                ),
            );

            return ExitCode::FAILURE;
        }

        $result1 = $this->compareArchives($diff, $io);
        $result2 = $this->compareContents($diff, $io);

        return $result1 + $result2;
    }

    /**
     * @return list<non-empty-string>
     */
    private static function getPaths(IO $io): array
    {
        $paths = [
            $io->getArgument(self::FIRST_PHAR_ARG)->asNonEmptyString(),
            $io->getArgument(self::SECOND_PHAR_ARG)->asNonEmptyString(),
        ];

        Assert::allFile($paths);

        return array_map(
            static fn (string $path) => Path::canonicalize($path),
            $paths,
        );
    }

    private function compareArchives(PharDiff $diff, IO $io): int
    {
        $io->comment('<info>Comparing the two archives... (do not check the signatures)</info>');

        $pharInfoA = $diff->getPharInfoA();
        $pharInfoB = $diff->getPharInfoB();

        self::renderArchive(
            $diff->getPharInfoA()->getFileName(),
            $pharInfoA,
            $io,
        );

        $io->newLine();

        self::renderArchive(
            $diff->getPharInfoB()->getFileName(),
            $pharInfoB,
            $io,
        );

        return ExitCode::FAILURE;
    }

    private function compareContents(PharDiff $diff, IO $io): int
    {
        $io->comment('<info>Comparing the two archives contents...</info>');

        $checkSumAlgorithm = $io->getOption(self::CHECK_OPTION)->asNullableNonEmptyString() ?? self::DEFAULT_CHECKSUM_ALGO;

        if ($io->hasOption('-c') || $io->hasOption('--check')) {
            return $diff->listChecksums($checkSumAlgorithm);
        }

        if ($io->getOption(self::GNU_DIFF_OPTION)->asBoolean()) {
            $diffResult = $diff->gnuDiff();
        } elseif ($io->getOption(self::GIT_DIFF_OPTION)->asBoolean()) {
            $diffResult = $diff->gitDiff();
        } else {
            $diffResult = $diff->listDiff();
        }

        if (null === $diffResult || [[], []] === $diffResult) {
            $io->success('The contents are identical');

            return ExitCode::SUCCESS;
        }

        if (is_string($diffResult)) {
            // Git or GNU diff: we don't have much control on the format
            $io->writeln($diffResult);

            return ExitCode::FAILURE;
        }

        $io->writeln(sprintf(
            '--- Files present in "%s" but not in "%s"',
            $diff->getPharInfoA()->getFileName(),
            $diff->getPharInfoB()->getFileName(),
        ));
        $io->writeln(sprintf(
            '+++ Files present in "%s" but not in "%s"',
            $diff->getPharInfoB()->getFileName(),
            $diff->getPharInfoA()->getFileName(),
        ));

        $io->newLine();

        self::renderPaths('-', $diff->getPharInfoA(), $diffResult[0], $io);
        self::renderPaths('+', $diff->getPharInfoB(), $diffResult[1], $io);

        $io->error(sprintf(
            '%d file(s) difference',
            count($diffResult[0]) + count($diffResult[1]),
        ));

        return ExitCode::FAILURE;
    }

    /**
     * @param list<non-empty-string> $paths
     */
    private static function renderPaths(string $symbol, PharInfo $pharInfo, array $paths, IO $io): void
    {
        foreach ($paths as $path) {
            /** @var PharFileInfo $file */
            $file = $pharInfo->getPhar()[str_replace($pharInfo->getRoot(), '', $path)];

            $compression = '<fg=red>[NONE]</fg=red>';

            foreach (CompressionAlgorithm::cases() as $compressionAlgorithm) {
                if (CompressionAlgorithm::NONE !== $compressionAlgorithm
                    && $file->isCompressed($compressionAlgorithm->value)
                ) {
                    $compression = "<fg=cyan>[{$compressionAlgorithm->name}]</fg=cyan>";
                    break;
                }
            }

            $fileSize = format_size($file->getCompressedSize());

            $io->writeln(
                sprintf(
                    '%s %s %s - %s',
                    $symbol,
                    $path,
                    $compression,
                    $fileSize,
                ),
            );
        }
    }

    private static function renderArchive(string $fileName, PharInfo $pharInfo, IO $io): void
    {
        $io->writeln(
            sprintf(
                '<comment>Archive: </comment><fg=cyan;options=bold>%s</>',
                $fileName,
            ),
        );

        PharInfoRenderer::renderCompression($pharInfo, $io);
        // Omit the signature
        PharInfoRenderer::renderMetadata($pharInfo, $io);
        PharInfoRenderer::renderContentsSummary($pharInfo, $io);
    }
}
