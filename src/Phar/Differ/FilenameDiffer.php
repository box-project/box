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

namespace KevinGH\Box\Phar\Differ;

use Fidry\Console\Input\IO;
use KevinGH\Box\Console\PharInfoRenderer;
use KevinGH\Box\Phar\PharInfo;
use KevinGH\Box\Pharaoh\PharDiff as ParagoniePharDiff;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Finder\Finder;
use function array_diff;
use function array_map;
use function array_sum;
use function explode;
use function iterator_to_array;
use function sprintf;
use function str_replace;

final class FilenameDiffer implements Differ
{
    public function diff(
        PharInfo $pharInfoA,
        PharInfo $pharInfoB,
        IO $io,
    ): void {
        $paragonieDiff = new ParagoniePharDiff($pharInfoA, $pharInfoB);
        $paragonieDiff->setVerbose(true);

        $pharAFiles = self::collectFiles($pharInfoA);
        $pharBFiles = self::collectFiles($pharInfoB);

        $diffResult = [
            array_diff($pharAFiles, $pharBFiles),
            array_diff($pharBFiles, $pharAFiles),
        ];

        if (0 === array_sum(array_map('count', $diffResult))) {
            $io->writeln(Differ::NO_DIFF_MESSAGE);

            return;
        }

        self::printDiff(
            $pharInfoA,
            $pharInfoB,
            $diffResult,
            $io,
        );
    }

    private static function printDiff(
        PharInfo $pharInfoA,
        PharInfo $pharInfoB,
        array $diffResult,
        IO $io,
    ): void {
        $io->writeln(sprintf(
            '--- Files present in "%s" but not in "%s"',
            $pharInfoA->getFileName(),
            $pharInfoB->getFileName(),
        ));
        $io->writeln(sprintf(
            '+++ Files present in "%s" but not in "%s"',
            $pharInfoB->getFileName(),
            $pharInfoA->getFileName(),
        ));

        $io->newLine();

        self::renderPaths('-', $pharInfoA, $diffResult[0], $io);
        self::renderPaths('+', $pharInfoB, $diffResult[1], $io);

        $diffCount = array_sum(array_map('count', $diffResult));

        $io->error(
            sprintf(
                '%d file(s) difference',
                $diffCount,
            ),
        );
    }

    /**
     * @param list<non-empty-string> $paths
     */
    private static function renderPaths(string $symbol, PharInfo $pharInfo, array $paths, IO $io): void
    {
        $bufferedOutput = new BufferedOutput(
            $io->getVerbosity(),
            $io->isDecorated(),
            $io->getOutput()->getFormatter(),
        );

        PharInfoRenderer::renderContent(
            $bufferedOutput,
            $pharInfo,
            false,
            false,
        );

        $lines = array_map(
            static fn (string $line) => '' === $line ? '' : $symbol.' '.$line,
            explode(
                PHP_EOL,
                $bufferedOutput->fetch(),
            ),
        );

        $io->writeln($lines);
    }

    /**
     * @return string[]
     */
    private static function collectFiles(PharInfo $pharInfo): array
    {
        $basePath = $pharInfo->getTmp().DIRECTORY_SEPARATOR;

        return array_map(
            static fn (SplFileInfo $fileInfo): string => str_replace($basePath, '', $fileInfo->getRealPath()),
            iterator_to_array(
                Finder::create()
                    ->files()
                    ->in($basePath)
                    ->ignoreDotFiles(false),
                false,
            ),
        );
    }
}
