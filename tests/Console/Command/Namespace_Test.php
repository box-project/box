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
use function KevinGH\Box\FileSystem\dump_file;
use KevinGH\Box\Test\CommandTestCase;

/**
 * @covers \KevinGH\Box\Console\Command\Namespace_
 */
class Namespace_Test extends CommandTestCase
{
    protected function getCommand(): Command
    {
        return new Namespace_();
    }

    public function test_it_show_the_ng(): void
    {
        dump_file('index.php', '');

        $this->commandTester->execute(
            [
                'command' => 'namespace',
            ],
        );

        $expected = 'KevinGH'."\n";

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }
}
