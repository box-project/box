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

use function current;
use function explode;
use KevinGH\Box\Console\IO\IO;

final class Namespace_ extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->setName('namespace');
        $this->setDescription('Prints the first part of the command namespace');
        $this->setHelp(
            <<<'HELP'
                This command is purely for debugging purposes to ensure it is scoped correctly.
                HELP,
        );
    }

    protected function executeCommand(IO $io): int
    {
        $namespace = current(explode('\\', self::class));

        $io->writeln($namespace);

        return self::SUCCESS;
    }
}
