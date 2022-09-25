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
use InvalidArgumentException;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Configuration\NoConfigurationFound;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\touch;
use KevinGH\Box\Json\JsonValidationException;
use KevinGH\Box\Test\CommandTestCase;
use Symfony\Component\Console\Input\InputOption;

/**
 * @covers \KevinGH\Box\Console\Command\ConfigOption
 */
class ConfigOptionTest extends CommandTestCase
{
    protected function getCommand(): Command
    {
        return new TestConfigurableCommand();
    }

    public function test_it_has_a_configure_option(): void
    {
        $options = $this->getCommand()->getConfiguration()->getOptions();

        $optionNames = array_map(
            static fn (InputOption $option) => $option->getName(),
            $options,
        );

        self::assertContains('config', $optionNames);
    }

    public function test_it_can_get_the_configuration(): void
    {
        touch('index.php');

        dump_file('box.json', '{"alias": "foo"}');

        $config = $this->executeAndGetConfig([]);

        $this->assertSame('foo', $config->getAlias());
    }

    public function test_it_can_get_the_configuration_with_a_custom_path(): void
    {
        touch('index.php');

        dump_file('mybox.json', '{"alias": "foo"}');

        $config = $this->executeAndGetConfig(['--config' => 'mybox.json']);

        $this->assertSame('foo', $config->getAlias());
    }

    public function test_it_throws_an_error_when_cannot_load_the_config(): void
    {
        touch('index.php');

        $this->expectException(NoConfigurationFound::class);
        $this->expectExceptionMessage('The configuration file could not be found.');

        $this->commandTester->execute([]);
    }

    public function test_it_loads_an_empty_configuration_if_no_configuration_is_allowed_and_no_config_file_is_found(): void
    {
        touch('index.php');

        $config = $this->executeAndGetConfig([], true);

        $this->assertSame($this->tmp.'/index.php', $config->getMainScriptPath());
    }

    public function test_it_throws_an_error_when_the_config_schema_is_invalid(): void
    {
        touch('index.php');

        dump_file('box.json', '{"foo": "foo"}');

        $this->expectException(JsonValidationException::class);
        $this->expectExceptionMessage('"'.$this->tmp.'/box.json" does not match the expected JSON schema:
  - The property foo is not defined and the definition does not allow additional properties');

        $this->executeAndGetConfig([]);
    }

    public function test_it_throws_an_error_when_the_config_is_invalid(): void
    {
        dump_file('box.json', '{}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The file "'.$this->tmp.'/index.php" does not exist.');

        $this->executeAndGetConfig([]);
    }

    /**
     * @param array<string, string> $input
     */
    private function executeAndGetConfig(array $input, bool $allowNoFile = false): Configuration
    {
        $command = $this->command;
        self::assertInstanceOf(TestConfigurableCommand::class, $command);

        if ($allowNoFile) {
            $command->allowNoFile = true;
        }

        $this->commandTester->execute($input);

        return $command->config;
    }
}
