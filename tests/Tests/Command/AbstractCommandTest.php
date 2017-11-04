<?php

namespace KevinGH\Box\Tests\Command;

use KevinGH\Box\Command\AbstractCommand;
use KevinGH\Box\Test\CommandTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractCommandTest extends CommandTestCase
{
    public function testVerbose()
    {
        $tester = $this->getTester();
        $tester->execute(
            array('command' => 'test'),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $this->assertEquals(
            <<<OUTPUT
! Error
* Item
? Info
  + Add

OUTPUT
            ,
            $this->getOutput($tester)
        );
    }

    public function testVerboseNone()
    {
        $tester = $this->getTester();
        $tester->execute(array('command' => 'test'));

        $this->assertEquals(
            <<<OUTPUT

OUTPUT
            ,
            $this->getOutput($tester)
        );
    }

    protected function getCommand()
    {
        return new TestAbstractCommand();
    }
}

class TestAbstractCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('test');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->putln('!', 'Error');
        $this->putln('*', 'Item');
        $this->putln('?', 'Info');
        $this->putln('+', 'Add');
    }
}
