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

use Closure;
use KevinGH\Box\Configuration;
use KevinGH\Box\Test\CommandTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @covers \KevinGH\Box\Console\Command\Configurable
 */
class ConfigurableTest extends CommandTestCase
{
    public function test_it_has_a_configure_option(): void
    {
        $definition = $this->getCommand()->getDefinition();

        $this->assertTrue($definition->hasOption('config'));
    }

    public function test_it_can_get_the_configuration(): void
    {
        file_put_contents('box.json', '{}');

        /** @var TestConfigurable $command */
        $command = $this->application->get('test');

        $input = new ArrayInput([]);
        $input->bind($command->getDefinition());

        $config = (Closure::bind(
            function (Configurable $command, InputInterface $input) {
                return $command->getConfig($input);
            },
            null,
            TestConfigurable::class
        )($command, $input));

        $this->assertInstanceOf(
            Configuration::class,
            $config
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new TestConfigurable('test');
    }
}
