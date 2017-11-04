<?php

namespace KevinGH\Box\Tests\Command;

use KevinGH\Box\Test\CommandTestCase;
use Symfony\Component\Console\Input\ArrayInput;

class ConfigurableTest extends CommandTestCase
{
    public function testConfigure()
    {
        $definition = $this->getCommand()->getDefinition();

        $this->assertTrue($definition->hasOption('configuration'));
    }

    public function testGetConfig()
    {
        file_put_contents('box.json', '{}');

        $command = $this->app->get('test');
        $input = new ArrayInput(array());
        $input->bind($command->getDefinition());

        $this->assertInstanceOf(
            'KevinGH\\Box\\Configuration',
            $this->callMethod($command, 'getConfig', array($input))
        );
    }

    protected function getCommand()
    {
        return new TestConfigurable('test');
    }
}
