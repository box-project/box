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

use KevinGH\Box\NotInstantiable;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Utility to configure the output formatter styles.
 *
 * @private
 */
final class OutputFormatterConfigurator
{
    use NotInstantiable;

    public static function configure(OutputInterface $output): void
    {
        $outputFormatter = $output->getFormatter();

        $outputFormatter->setStyle(
            'recommendation',
            new OutputFormatterStyle('black', 'yellow')
        );
        $outputFormatter->setStyle(
            'warning',
            new OutputFormatterStyle('white', 'red')
        );
    }
}
