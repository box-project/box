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

namespace KevinGH\Box\Command;

use KevinGH\Box\Test\CommandTestCase;
use Phar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversNothing
 */
class RemoveTest extends CommandTestCase
{
    public function testExecute(): void
    {
        $phar = new Phar('test.phar');
        $phar->addFromString('a.php', '');
        $phar->addFromString('b.php', '');
        $phar->addFromString('c.php', '');
        $phar->addFromString('d.php', '');

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'remove',
                'phar' => 'test.phar',
                'file' => [
                    'b.php',
                    'd.php',
                    'x.php',
                ],
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'
Removing files from the Phar...
Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $phar = new Phar('test.phar');

        $this->assertTrue(isset($phar['a.php']));
        $this->assertFalse(isset($phar['b.php']));
        $this->assertTrue(isset($phar['c.php']));
        $this->assertFalse(isset($phar['d.php']));
    }

    public function testExecuteNotExist(): void
    {
        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'remove',
                'phar' => 'test.phar',
                'file' => ['b.php'],
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'
Removing files from the Phar...
The path "test.phar" is not a file or does not exist.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    protected function getCommand()
    {
        return new Remove();
    }
}
