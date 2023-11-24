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

use BenchTest\Console\Php\PhpSettingsHandler;
use BenchTest\NotInstantiable;
use Fidry\Console\IO;
use Symfony\Component\Console\Logger\ConsoleLogger;

/**
 * @internal
 */
final class PhpSettingsChecker
{
    use NotInstantiable;

    public static function check(IO $io): void
    {
        (new PhpSettingsHandler(
            new ConsoleLogger(
                $io->getOutput(),
            ),
        ))->check();
    }
}
