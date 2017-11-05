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
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @coversNothing
 */
class ConfigurableTest extends CommandTestCase
{
    public function testConfigure(): void
    {
        $definition = $this->getCommand()->getDefinition();

        $this->assertTrue($definition->hasOption('configuration'));
    }

    public function testGetConfig(): void
    {
        file_put_contents('box.json', '{}');

        $command = $this->app->get('test');
        $input = new ArrayInput([]);
        $input->bind($command->getDefinition());

        $this->assertInstanceOf(
            'KevinGH\\Box\\Configuration',
            $this->callMethod($command, 'getConfig', [$input])
        );
    }

    protected function getCommand(): Command
    {
        return new TestConfigurable('test');
    }
}
