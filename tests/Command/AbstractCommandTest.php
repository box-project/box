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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversNothing
 */
class AbstractCommandTest extends CommandTestCase
{
    public function testVerbose(): void
    {
        $tester = $this->getCommandTester();
        $tester->execute(
            ['command' => 'test'],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $this->assertSame(
            <<<'OUTPUT'
! Error
* Item
? Info
  + Add

OUTPUT
            ,
            $this->getOutput($tester)
        );
    }

    public function testVerboseNone(): void
    {
        $tester = $this->getCommandTester();
        $tester->execute(['command' => 'test']);

        $this->assertSame(
            <<<'OUTPUT'

OUTPUT
            ,
            $this->getOutput($tester)
        );
    }

    protected function getCommand(): Command
    {
        return new TestAbstractCommand();
    }
}

class TestAbstractCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName('test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->putln('!', 'Error');
        $this->putln('*', 'Item');
        $this->putln('?', 'Info');
        $this->putln('+', 'Add');
    }
}
