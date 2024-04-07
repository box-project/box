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

namespace KevinGH\Box\Console\Command;

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use function current;
use function explode;

final class NamespaceCommand implements Command
{
    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'namespace',
            'Prints the first part of the command namespace',
            <<<'HELP'
                This command is purely for debugging purposes to ensure it is scoped correctly.
                HELP,
        );
    }

    public function execute(IO $io): int
    {
        $namespace = current(explode('\\', self::class));

        $io->writeln($namespace);

        return ExitCode::SUCCESS;
    }
}
