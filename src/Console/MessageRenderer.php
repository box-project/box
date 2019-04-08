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

use function array_map;
use Assert\Assertion;
use function count;
use KevinGH\Box\Console\IO\IO;
use KevinGH\Box\NotInstantiable;
use function sprintf;

/**
 * Utility to writing on the console output the configuration recommendations and warnings.
 *
 * @private
 */
final class MessageRenderer
{
    use NotInstantiable;

    /**
     * @param string[] $recommendations
     * @param string[] $warnings
     */
    public static function render(IO $io, array $recommendations, array $warnings): void
    {
        Assertion::allString($recommendations);
        Assertion::allString($warnings);

        $renderMessage = static function (string $message): string {
            return "    - $message";
        };

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
                array_map($renderMessage, $recommendations)
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
                array_map($renderMessage, $warnings)
            );
        }
    }
}
