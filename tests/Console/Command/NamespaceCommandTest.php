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
use Fidry\Console\ExitCode;
use Fidry\FileSystem\FS;
use KevinGH\Box\Test\CommandTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(NamespaceCommand::class)]
class NamespaceCommandTest extends CommandTestCase
{
    protected function getCommand(): Command
    {
        return new NamespaceCommand();
    }

    public function test_it_show_the_ng(): void
    {
        FS::dumpFile('index.php');

        $this->commandTester->execute(
            [
                'command' => 'namespace',
            ],
        );

        $expected = 'KevinGH'."\n";

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }
}
