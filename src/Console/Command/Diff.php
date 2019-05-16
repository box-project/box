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

use function array_filter;
use function array_flip;
use Assert\Assertion;
use function count;
use function is_string;
use function KevinGH\Box\check_php_settings;
use KevinGH\Box\Console\IO\IO;
use KevinGH\Box\Console\PharInfoRenderer;
use function KevinGH\Box\format_size;
use function KevinGH\Box\get_phar_compression_algorithms;
use KevinGH\Box\PharInfo\PharDiff;
use KevinGH\Box\PharInfo\PharInfo;
use PharFileInfo;
use function sprintf;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * @private
 */
final class Diff extends BaseCommand
{
    private const FIRST_PHAR_ARG = 'pharA';
    private const SECOND_PHAR_ARG = 'pharB';

    private const LIST_FILES_DIFF_OPTION = 'list-diff';
    private const GIT_DIFF_OPTION = 'git-diff';
    private const GNU_DIFF_OPTION = 'gnu-diff';
    private const CHECK_OPTION = 'check';

    private static $FILE_ALGORITHMS;

    /**
     * {@inheritdoc}
     */
    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        if (null === self::$FILE_ALGORITHMS) {
            self::$FILE_ALGORITHMS = array_flip(array_filter(get_phar_compression_algorithms()));
        }
    }

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
            null,
            InputOption::VALUE_NONE,
            'Displays a GNU diff'
        );
        $this->addOption(
            self::GIT_DIFF_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Displays a Git diff'
        );
        $this->addOption(
            self::LIST_FILES_DIFF_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Displays a list of file names diff (default)'
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
    protected function executeCommand(IO $io): int
    {
        check_php_settings($io);

        $input = $io->getInput();

        /** @var string[] $paths */
        $paths = [
            $input->getArgument(self::FIRST_PHAR_ARG),
            $input->getArgument(self::SECOND_PHAR_ARG),
        ];

        Assertion::allFile($paths);

        try {
            $diff = new PharDiff(...$paths);
        } catch (Throwable $throwable) {
            if ($io->isDebug()) {
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

        $result1 = $this->compareArchives($diff, $io);
        $result2 = $this->compareContents($input, $diff, $io);

        return $result1 + $result2;
    }

    private function compareArchives(PharDiff $diff, IO $io): int
    {
        $io->comment('<info>Comparing the two archives... (do not check the signatures)</info>');

        $pharInfoA = $diff->getPharA()->getPharInfo();
        $pharInfoB = $diff->getPharB()->getPharInfo();

        if ($pharInfoA->equals($pharInfoB)) {
            $io->success('The two archives are identical');

            return 0;
        }

        $this->renderArchive(
            $diff->getPharA()->getFileName(),
            $pharInfoA,
            $io
        );

        $io->newLine();

        $this->renderArchive(
            $diff->getPharB()->getFileName(),
            $pharInfoB,
            $io
        );

        return 1;
    }

    private function compareContents(InputInterface $input, PharDiff $diff, IO $io): int
    {
        $io->comment('<info>Comparing the two archives contents...</info>');

        if ($input->hasParameterOption(['-c', '--check'])) {
            return $diff->listChecksums($input->getOption(self::CHECK_OPTION) ?? 'sha384');
        }

        if ($input->getOption(self::GNU_DIFF_OPTION)) {
            $diffResult = $diff->gnuDiff();
        } elseif ($input->getOption(self::GIT_DIFF_OPTION)) {
            $diffResult = $diff->gitDiff();
        } else {
            $diffResult = $diff->listDiff();
        }

        if (null === $diffResult || [[], []] === $diffResult) {
            $io->success('The contents are identical');

            return 0;
        }

        if (is_string($diffResult)) {
            // Git or GNU diff: we don't have much control on the format
            $io->writeln($diffResult);

            return 1;
        }

        $io->writeln(sprintf(
            '--- Files present in "%s" but not in "%s"',
            $diff->getPharA()->getFileName(),
            $diff->getPharB()->getFileName()
        ));
        $io->writeln(sprintf(
            '+++ Files present in "%s" but not in "%s"',
            $diff->getPharB()->getFileName(),
            $diff->getPharA()->getFileName()
        ));

        $io->newLine();

        $renderPaths = static function (string $symbol, PharInfo $pharInfo, array $paths, IO $io): void {
            foreach ($paths as $path) {
                /** @var PharFileInfo $file */
                $file = $pharInfo->getPhar()[str_replace($pharInfo->getRoot(), '', $path)];

                $compression = '<fg=red>[NONE]</fg=red>';

                foreach (self::$FILE_ALGORITHMS as $code => $name) {
                    if ($file->isCompressed($code)) {
                        $compression = "<fg=cyan>[$name]</fg=cyan>";
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
                        $fileSize
                    )
                );
            }
        };

        $renderPaths('-', $diff->getPharA()->getPharInfo(), $diffResult[0], $io);
        $renderPaths('+', $diff->getPharB()->getPharInfo(), $diffResult[1], $io);

        $io->error(sprintf(
            '%d file(s) difference',
            count($diffResult[0]) + count($diffResult[1])
        ));

        return 1;
    }

    private function renderArchive(string $fileName, PharInfo $pharInfo, IO $io): void
    {
        $io->writeln(
            sprintf(
                '<comment>Archive: </comment><fg=cyan;options=bold>%s</>',
                $fileName
            )
        );

        PharInfoRenderer::renderCompression($pharInfo, $io);
        // Omit the signature
        PharInfoRenderer::renderMetadata($pharInfo, $io);
        PharInfoRenderer::renderContentsSummary($pharInfo, $io);
    }
}
