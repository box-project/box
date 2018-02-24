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

use KevinGH\Box\Console\Application;
use KevinGH\Box\Console\DisplayNormalizer;
use KevinGH\Box\Test\CommandTestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \KevinGH\Box\Console\Command\Validate
 *
 * @runTestsInSeparateProcesses
 */
class ValidateTest extends CommandTestCase
{
    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->commandTester = new CommandTester((new Application())->get('validate'));
    }

    public function test_it_validates_a_given_file(): void
    {
        touch('foo');
        file_put_contents('test.json', '{"files": ["foo"]}');

        $this->commandTester->execute(
            [
                'command' => 'validate',
                '--config' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

 // Loading the configuration file "test.json".

The configuration file passed validation.

OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_an_unknown_file_is_invalid(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'validate',
            ]
        );

        $expected = <<<'OUTPUT'
The configuration file failed validation: The configuration file could not be found.

OUTPUT;

        $this->assertSame($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_an_unknown_file_is_invalid_in_verbose_mode(): void
    {
        try {
            $this->commandTester->execute(
                [
                    'command' => 'validate',
                ],
                [
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The configuration file failed validation: The configuration file could not be found.',
                $exception->getMessage()
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertNotNull($exception->getPrevious());
        }
    }

    public function test_an_invalid_JSON_file_is_invalid(): void
    {
        file_put_contents('box.json.dist', '{');

        $this->commandTester->execute(
            [
                'command' => 'validate',
            ]
        );

        $expected = <<<'OUTPUT'

 // Loading the configuration file "box.json.dist".

The configuration file failed validation: Parse error on line 1:
{
^
Expected one of: 'STRING', '}'

OUTPUT;

        $actual = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));

        $actual = preg_replace(
            '/\s\/\/ Loading the configuration file(\n.+)+box\.json\.dist[comment\<\>\n\s\/]*"\./',
            ' // Loading the configuration file "box.json.dist".',
            $actual
        );

        $this->assertSame($expected, $actual);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_an_invalid_JSON_file_is_invalid_in_verbose_mode(): void
    {
        file_put_contents('box.json.dist', '{');

        try {
            $this->commandTester->execute(
                [
                    'command' => 'validate',
                ],
                [
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $expected = <<<'OUTPUT'
The configuration file failed validation: Parse error on line 1:
{
^
Expected one of: 'STRING', '}'
OUTPUT;

            $this->assertSame($expected, $exception->getMessage());
            $this->assertSame(0, $exception->getCode());
            $this->assertNotNull($exception->getPrevious());
        }
    }

    public function test_an_incorrect_config_file_is_invalid(): void
    {
        file_put_contents('box.json', '{"test": true}');

        $this->commandTester->execute(
            [
                'command' => 'validate',
            ]
        );

        $expected = str_replace(
            '/path/to',
            $this->tmp,
            <<<'EOF'

 // Loading the configuration file "box.json.dist".

The configuration file failed validation: "/path/to/box.json" does not match the expected JSON schema:

  - The property test is not defined and the definition does not allow additional properties

EOF
        );

        $actual = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));

        $actual = preg_replace(
            '/\s\/\/ Loading the configuration file(\n.+)+box\.json[comment\<\>\n\s\/]*"\./',
            ' // Loading the configuration file "box.json.dist".',
            $actual
        );

        $this->assertSame($expected, $actual);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_an_incorrect_config_file_is_invalid_in_verbose_mode(): void
    {
        file_put_contents('box.json', '{"test": true}');

        try {
            $this->commandTester->execute(
                [
                    'command' => 'validate',
                ],
                [
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                str_replace(
                    '/path/to',
                    $this->tmp,
                    <<<'EOF'
The configuration file failed validation: "/path/to/box.json" does not match the expected JSON schema:
  - The property test is not defined and the definition does not allow additional properties
EOF
                ),
                $exception->getMessage()
            );
            $this->assertSame(0, $exception->getCode());
            $this->assertNotNull($exception->getPrevious());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Validate();
    }
}
