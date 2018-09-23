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
            $io->writeln('Recommendations:');

            $io->writeln(
                array_map(
                    function (string $recommendation): string {
                        return "    - <recommendation>$recommendation</recommendation>";
                    },
                    $recommendations
                )
            );
        }

        if ([] === $warnings) {
            $io->writeln('No warning found.');
        } else {
            $io->writeln('Warnings:');

            $io->writeln(
                array_map(
                    function (string $warning): string {
                        return "    - <warning>$warning</warning>";
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
