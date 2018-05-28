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

use Assert\InvalidArgumentException;
use Closure;
use KevinGH\Box\Configuration;
use KevinGH\Box\Json\JsonValidationException;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Throwable\Exception\NoConfigurationFound;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \KevinGH\Box\Console\Command\Configurable
 */
class ConfigurableTest extends CommandTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new TestConfigurable('test');
    }

    public function test_it_has_a_configure_option(): void
    {
        $definition = $this->getCommand()->getDefinition();

        $this->assertTrue($definition->hasOption('config'));
    }

    public function test_it_can_get_the_configuration(): void
    {
        touch('index.php');

        file_put_contents('box.json', '{"alias": "foo"}');

        /** @var TestConfigurable $command */
        $command = $this->application->get('test');

        $input = new ArrayInput([]);
        $input->bind($command->getDefinition());

        $output = new NullOutput();

        /** @var Configuration $config */
        $config = (Closure::bind(
            function (Configurable $command, InputInterface $input, OutputInterface $output): Configuration {
                return $command->getConfig($input, $output);
            },
            null,
            TestConfigurable::class
        )($command, $input, $output));

        $this->assertInstanceOf(
            Configuration::class,
            $config
        );

        $this->assertSame('foo', $config->getAlias());
    }

    public function test_it_can_get_the_configuration__with_a_custom_path(): void
    {
        touch('index.php');

        file_put_contents('mybox.json', '{"alias": "foo"}');

        /** @var TestConfigurable $command */
        $command = $this->application->get('test');

        $input = new ArrayInput(['--config' => 'mybox.json']);
        $input->bind($command->getDefinition());

        $output = new NullOutput();

        /** @var Configuration $config */
        $config = (Closure::bind(
            function (Configurable $command, InputInterface $input, OutputInterface $output): Configuration {
                return $command->getConfig($input, $output);
            },
            null,
            TestConfigurable::class
        )($command, $input, $output));

        $this->assertInstanceOf(
            Configuration::class,
            $config
        );

        $this->assertSame('foo', $config->getAlias());
    }

    public function test_it_throws_an_error_when_cannot_load_the_config(): void
    {
        touch('index.php');

        /** @var TestConfigurable $command */
        $command = $this->application->get('test');

        $input = new ArrayInput([]);
        $input->bind($command->getDefinition());

        $output = new NullOutput();

        try {
            (Closure::bind(
                function (Configurable $command, InputInterface $input, OutputInterface $output): Configuration {
                    return $command->getConfig($input, $output);
                },
                null,
                TestConfigurable::class
            )($command, $input, $output));

            $this->fail('Expected exception to be thrown.');
        } catch (NoConfigurationFound $exception) {
            $this->assertSame(
                'The configuration file could not be found.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_loads_an_empty_configuration_if_no_configuration_is_allowed_and_no_config_file_is_found(): void
    {
        touch('index.php');

        /** @var TestConfigurable $command */
        $command = $this->application->get('test');

        $input = new ArrayInput([]);
        $input->bind($command->getDefinition());

        $output = new NullOutput();

        /** @var Configuration $config */
        $config = (Closure::bind(
            function (Configurable $command, InputInterface $input, OutputInterface $output): Configuration {
                return $command->getConfig($input, $output, true);
            },
            null,
            TestConfigurable::class
        )($command, $input, $output));

        $this->assertInstanceOf(
            Configuration::class,
            $config
        );

        $this->assertSame($this->tmp.'/index.php', $config->getMainScriptPath());
    }

    public function test_it_throws_an_error_when_the_config_schema_is_invalid(): void
    {
        touch('index.php');

        file_put_contents('box.json', '{"foo": "foo"}');

        /** @var TestConfigurable $command */
        $command = $this->application->get('test');

        $input = new ArrayInput([]);
        $input->bind($command->getDefinition());

        $output = new NullOutput();

        try {
            (Closure::bind(
                function (Configurable $command, InputInterface $input, OutputInterface $output): Configuration {
                    return $command->getConfig($input, $output);
                },
                null,
                TestConfigurable::class
            )($command, $input, $output));

            $this->fail('Expected exception to be thrown.');
        } catch (JsonValidationException $exception) {
            $this->assertSame(
                '"'.$this->tmp.'/box.json" does not match the expected JSON schema:
  - The property foo is not defined and the definition does not allow additional properties',
                $exception->getMessage()
            );
        }
    }

    public function test_it_throws_an_error_when_the_config_is_invalid(): void
    {
        file_put_contents('box.json', '{}');

        /** @var TestConfigurable $command */
        $command = $this->application->get('test');

        $input = new ArrayInput([]);
        $input->bind($command->getDefinition());

        $output = new NullOutput();

        try {
            (Closure::bind(
                function (Configurable $command, InputInterface $input, OutputInterface $output): Configuration {
                    return $command->getConfig($input, $output);
                },
                null,
                TestConfigurable::class
            )($command, $input, $output));

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "'.$this->tmp.'/index.php" was expected to exist.',
                $exception->getMessage()
            );
        }
    }
}
