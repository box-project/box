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

use Symfony\Component\Console\Style\SymfonyStyle;
use function array_map;
use function count;
use function sprintf;

/**
 * @private
 */
final class MessageRenderer
{
    public static function render(SymfonyStyle $io, array $recommendations, array $warnings): void
    {
        if ([] === $recommendations) {
            $io->writeln('No recommendation found.');
        } else {
            $io->writeln(
                sprintf(
                    '💡  <recommendation>%d %s found:</recommendation>',
                    count($recommendations),
                    count($recommendations) > 1 ? 'recommendations' : 'recommendation'
                )
            );

            $io->writeln(
                array_map(
                    static function (string $recommendation): string {
                        return "    - $recommendation";
                    },
                    $recommendations
                )
            );
        }

        if ([] === $warnings) {
            $io->writeln('No warning found.');
        } else {
            $io->writeln(
                sprintf(
                    '⚠️  <warning>%d %s found:</warning>',
                    count($warnings),
                    count($warnings) > 1 ? 'warnings' : 'warning'
                )
            );

            $io->writeln(
                array_map(
                    static function (string $warning): string {
                        return "    - $warning";
                    },
                    $warnings
                )
            );
        }
    }

    private function __construct()
    {
    }
}
