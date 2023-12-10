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
use Fidry\FileSystem\FS;
use InvalidArgumentException;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Configuration\NoConfigurationFound;
use KevinGH\Box\Json\JsonValidationException;
use KevinGH\Box\Test\CommandTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 */
#[CoversClass(ConfigOption::class)]
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
        FS::touch('index.php');

        FS::dumpFile('box.json', '{"alias": "foo"}');

        $config = $this->executeAndGetConfig([]);

        self::assertSame('foo', $config->getAlias());
    }

    public function test_it_can_get_the_configuration_with_a_custom_path(): void
    {
        FS::touch('index.php');

        FS::dumpFile('mybox.json', '{"alias": "foo"}');

        $config = $this->executeAndGetConfig(['--config' => 'mybox.json']);

        self::assertSame('foo', $config->getAlias());
    }

    public function test_it_throws_an_error_when_cannot_load_the_config(): void
    {
        FS::touch('index.php');

        $this->expectException(NoConfigurationFound::class);
        $this->expectExceptionMessage('The configuration file could not be found.');

        $this->commandTester->execute([]);
    }

    public function test_it_loads_an_empty_configuration_if_no_configuration_is_allowed_and_no_config_file_is_found(): void
    {
        FS::touch('index.php');

        $config = $this->executeAndGetConfig([], true);

        self::assertSame($this->tmp.'/index.php', $config->getMainScriptPath());
    }

    public function test_it_throws_an_error_when_the_config_schema_is_invalid(): void
    {
        FS::touch('index.php');

        FS::dumpFile('box.json', '{"foo": "foo"}');

        $this->expectException(JsonValidationException::class);
        $this->expectExceptionMessage('"'.$this->tmp.'/box.json" does not match the expected JSON schema:
  - The property foo is not defined and the definition does not allow additional properties');

        $this->executeAndGetConfig([]);
    }

    public function test_it_throws_an_error_when_the_config_is_invalid(): void
    {
        FS::dumpFile('box.json', '{}');

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
