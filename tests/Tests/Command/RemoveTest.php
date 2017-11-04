<?php

namespace KevinGH\Box\Tests\Command;

use KevinGH\Box\Command\Remove;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveTest extends CommandTestCase
{
    public function testExecute()
    {
        $phar = new Phar('test.phar');
        $phar->addFromString('a.php', '');
        $phar->addFromString('b.php', '');
        $phar->addFromString('c.php', '');
        $phar->addFromString('d.php', '');

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'remove',
                'phar' => 'test.phar',
                'file' => array(
                    'b.php',
                    'd.php',
                    'x.php'
                )
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $expected = <<<OUTPUT
Removing files from the Phar...
Done.

OUTPUT;

        $this->assertEquals($expected, $this->getOutput($tester));

        $phar = new Phar('test.phar');

        $this->assertTrue(isset($phar['a.php']));
        $this->assertFalse(isset($phar['b.php']));
        $this->assertTrue(isset($phar['c.php']));
        $this->assertFalse(isset($phar['d.php']));
    }

    public function testExecuteNotExist()
    {
        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'remove',
                'phar' => 'test.phar',
                'file' => array('b.php')
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $expected = <<<OUTPUT
Removing files from the Phar...
The path "test.phar" is not a file or does not exist.

OUTPUT;

        $this->assertEquals($expected, $this->getOutput($tester));
    }

    protected function getCommand()
    {
        return new Remove();
    }
}
