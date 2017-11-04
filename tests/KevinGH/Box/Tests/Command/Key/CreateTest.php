<?php

namespace KevinGH\Box\Tests\Command\Key;

use KevinGH\Box\Command\Key\Create;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\FixedResponse;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTest extends CommandTestCase
{
    public function testExecute()
    {
        $this->app->getHelperSet()->set(new FixedResponse('test'));

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'key:create',
                '--bits' => 512,
                '--out' => 'test.key',
                '--public' => 'test.pub',
                '--prompt' => true
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $expected = <<<OUTPUT
Generating 512 bit private key...
Writing private key...
Writing public key...

OUTPUT;

        $this->assertEquals($expected, $this->getOutput($tester));
        $this->assertRegExp('/PRIVATE KEY/', file_get_contents('test.key'));
        $this->assertRegExp('/PUBLIC KEY/', file_get_contents('test.pub'));
    }

    protected function getCommand()
    {
        return new Create();
    }
}
