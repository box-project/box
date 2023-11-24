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

namespace BenchTest\Console;

use BenchTest\NotInstantiable;
use Fidry\Console\IO;
use Webmozart\Assert\Assert;
use function array_map;
use function count;
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
        Assert::allString($recommendations);
        Assert::allString($warnings);

        $renderMessage = static fn (string $message): string => "    - {$message}";

        if ([] === $recommendations) {
            $io->writeln('No recommendation found.');
        } else {
            $io->writeln(
                sprintf(
                    '💡  <recommendation>%d %s found:</recommendation>',
                    count($recommendations),
                    count($recommendations) > 1 ? 'recommendations' : 'recommendation',
                ),
            );

            $io->writeln(
                array_map($renderMessage, $recommendations),
            );
        }

        if ([] === $warnings) {
            $io->writeln('No warning found.');
        } else {
            $io->writeln(
                sprintf(
                    '⚠️  <warning>%d %s found:</warning>',
                    count($warnings),
                    count($warnings) > 1 ? 'warnings' : 'warning',
                ),
            );

            $io->writeln(
                array_map($renderMessage, $warnings),
            );
        }

        $io->newLine();
    }
}
