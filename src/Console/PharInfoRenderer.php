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

namespace KevinGH\Box\Console;

use function array_filter;
use function array_key_last;
use function array_sum;
use function count;
use Fidry\Console\Input\IO;
use function KevinGH\Box\format_size;
use KevinGH\Box\NotInstantiable;
use KevinGH\Box\PharInfo\PharInfo;
use function key;
use function round;
use function Safe\filesize;
use function Safe\sprintf;

/**
 * Utility to write to the console output various PHAR related pieces of information.
 *
 * @private
 */
final class PharInfoRenderer
{
    use NotInstantiable;

    public static function renderCompression(PharInfo $pharInfo, IO $io): void
    {
        $count = array_filter($pharInfo->getCompressionCount());
        $totalCount = array_sum($count);

        if (1 === count($count)) {
            $io->writeln(
                sprintf(
                    '<comment>Compression:</comment> %s',
                    key($count),
                ),
            );

            return;
        }

        $io->writeln('<comment>Compression:</comment>');
        $lastAlgorithmName = array_key_last($count);

        $totalPercentage = 100;

        foreach ($count as $algorithmName => $nbrOfFiles) {
            if ($lastAlgorithmName === $algorithmName) {
                $percentage = $totalPercentage;
            } else {
                $percentage = round($nbrOfFiles * 100 / $totalCount, 2);

                $totalPercentage -= $percentage;
            }

            $io->writeln(
                sprintf(
                    '  - %s (%0.2f%%)',
                    $algorithmName,
                    $percentage,
                ),
            );
        }
    }

    public static function renderSignature(PharInfo $pharInfo, IO $io): void
    {
        $signature = $pharInfo->getPhar()->getSignature();

        if (false === $signature) {
            $io->writeln('<comment>Signature unreadable</comment>');

            return;
        }

        $io->writeln(
            sprintf(
                '<comment>Signature:</comment> %s',
                $signature['hash_type'],
            ),
        );
        $io->writeln(
            sprintf(
                '<comment>Signature Hash:</comment> %s',
                $signature['hash'],
            ),
        );
    }

    public static function renderMetadata(PharInfo $pharInfo, IO $io): void
    {
        $metadata = $pharInfo->getNormalizedMetadata();

        if (null === $metadata) {
            $io->writeln('<comment>Metadata:</comment> None');
        } else {
            $io->writeln('<comment>Metadata:</comment>');
            $io->writeln($metadata);
        }
    }

    public static function renderContentsSummary(PharInfo $pharInfo, IO $io): void
    {
        $count = array_filter($pharInfo->getCompressionCount());
        $totalCount = array_sum($count);

        $io->writeln(
            sprintf(
                '<comment>Contents:</comment>%s (%s)',
                1 === $totalCount ? ' 1 file' : " $totalCount files",
                format_size(
                    filesize($pharInfo->getPhar()->getPath()),
                ),
            ),
        );
    }
}
