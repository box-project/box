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

use Fidry\Console\IO;
use KevinGH\Box\NotInstantiable;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Utility to configure the output formatter styles.
 *
 * @private
 */
final class OutputFormatterConfigurator
{
    use NotInstantiable;

    public static function configure(IO $io): void
    {
        self::configureFormatter(
            $io->getOutput()->getFormatter(),
        );
    }

    public static function configureFormatter(OutputFormatterInterface $outputFormatter): void
    {
        $outputFormatter->setStyle(
            'recommendation',
            new OutputFormatterStyle('black', 'yellow'),
        );
        $outputFormatter->setStyle(
            'warning',
            new OutputFormatterStyle('white', 'red'),
        );
        $outputFormatter->setStyle(
            'diff-expected',
            new OutputFormatterStyle('green'),
        );
        $outputFormatter->setStyle(
            'diff-actual',
            new OutputFormatterStyle('red'),
        );
    }
}
