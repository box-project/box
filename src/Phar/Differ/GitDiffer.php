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

use Fidry\Console\IO;
use KevinGH\Box\Console\Command\ExtractCommand;
use KevinGH\Box\Phar\PharInfo;
use function array_filter;
use function explode;
use function implode;
use function sprintf;
use function str_starts_with;

final class GitDiffer implements Differ
{
    public function diff(PharInfo $pharInfoA, PharInfo $pharInfoB, IO $io): void
    {
        $gitDiff = ProcessCommandBasedDiffer::getDiff(
            $pharInfoA,
            $pharInfoB,
            'git diff --no-index',
        );

        if (null === $gitDiff) {
            $io->writeln(Differ::NO_DIFF_MESSAGE);

            return;
        }

        $separator = 'diff --git ';

        $diffLines = explode(
            $separator,
            $gitDiff,
        );

        $pharMetaLine = sprintf(
            'a%2$s/%1$s b%3$s/%1$s',
            ExtractCommand::PHAR_META_PATH,
            $pharInfoA->getFileName(),
            $pharInfoB->getFileName(),
        );

        $filteredLines = array_filter(
            $diffLines,
            static fn (string $line) => !str_starts_with($line, $pharMetaLine)
        );

        $filteredDiff = implode($separator, $filteredLines);

        $io->writeln('' === $filteredDiff ? Differ::NO_DIFF_MESSAGE : $filteredDiff);
    }
}
