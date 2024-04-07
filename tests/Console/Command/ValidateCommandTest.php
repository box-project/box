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
use Fidry\Console\ExitCode;
use Fidry\FileSystem\FS;
use KevinGH\Box\Console\DisplayNormalizer;
use KevinGH\Box\Console\MessageRenderer;
use KevinGH\Box\Test\CommandTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use function str_replace;

/**
 * @internal
 */
#[CoversClass(ValidateCommand::class)]
#[CoversClass(MessageRenderer::class)]
class ValidateCommandTest extends CommandTestCase
{
    protected function getCommand(): Command
    {
        return new ValidateCommand();
    }

    public function test_it_validates_a_given_file(): void
    {
        FS::touch('index.php');
        FS::dumpFile('test.json', '{}');

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

             // Loading the configuration file "test.json".

            No recommendation found.
            No warning found.

             [OK] The configuration file passed the validation.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_reports_the_recommendations_found(): void
    {
        FS::touch('index.php');
        FS::dumpFile(
            'test.json',
            <<<'JSON'
                {
                    "key": null
                }
                JSON,
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

             // Loading the configuration file "test.json".

            💡  <recommendation>1 recommendation found:</recommendation>
                - The setting "key" has been set but is unnecessary since the signing algorithm is not "OPENSSL".
            No warning found.

             ! [CAUTION] The configuration file passed the validation with recommendations.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::FAILURE);
    }

    public function test_it_does_not_fail_when_recommendations_are_found_but_ignore_message_is_passed(): void
    {
        FS::touch('index.php');
        FS::dumpFile(
            'test.json',
            <<<'JSON'
                {
                    "key": null
                }
                JSON,
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
                '--ignore-recommendations-and-warnings' => null,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

             // Loading the configuration file "test.json".

            💡  <recommendation>1 recommendation found:</recommendation>
                - The setting "key" has been set but is unnecessary since the signing algorithm is not "OPENSSL".
            No warning found.

             ! [CAUTION] The configuration file passed the validation with recommendations.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_reports_the_warnings_found(): void
    {
        FS::touch('index.php');
        FS::dumpFile(
            'test.json',
            <<<'JSON'
                {
                    "key": "key-file"
                }
                JSON,
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

             // Loading the configuration file "test.json".

            No recommendation found.
            ⚠️  <warning>1 warning found:</warning>
                - The setting "key" has been set but is ignored since the signing algorithm is not "OPENSSL".

             ! [CAUTION] The configuration file passed the validation with warnings.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::FAILURE);
    }

    public function test_it_does_not_fail_when_warnings_are_found_but_ignore_message_is_passed(): void
    {
        FS::touch('index.php');
        FS::dumpFile(
            'test.json',
            <<<'JSON'
                {
                    "key": "key-file"
                }
                JSON,
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
                '--ignore-recommendations-and-warnings' => null,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

             // Loading the configuration file "test.json".

            No recommendation found.
            ⚠️  <warning>1 warning found:</warning>
                - The setting "key" has been set but is ignored since the signing algorithm is not "OPENSSL".

             ! [CAUTION] The configuration file passed the validation with warnings.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_reports_the_recommendations_and_warnings_found(): void
    {
        FS::touch('index.php');
        FS::dumpFile(
            'test.json',
            <<<'JSON'
                {
                    "check-requirements": true
                }
                JSON,
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

             // Loading the configuration file "test.json".

            💡  <recommendation>1 recommendation found:</recommendation>
                - The "check-requirements" setting can be omitted since is set to its default value
            ⚠️  <warning>1 warning found:</warning>
                - The requirement checker could not be used because the composer.json and composer.lock file could not be found.

             ! [CAUTION] The configuration file passed the validation with recommendations
             !           and warnings.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::FAILURE);
    }

    public function test_an_unknown_file_is_invalid(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'validate',
            ],
        );

        $expected = <<<'OUTPUT'
            The configuration file failed validation: The configuration file could not be found.

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::FAILURE);
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
                ],
            );

            self::fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'The configuration file failed validation: The configuration file could not be found.',
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());
            self::assertNotNull($exception->getPrevious());
        }
    }

    public function test_an_invalid_json_file_is_invalid(): void
    {
        FS::dumpFile('box.json', '{');

        $this->commandTester->execute(
            [
                'command' => 'validate',
            ],
        );

        $expected = <<<'OUTPUT'

             // Loading the configuration file "box.json".

            The configuration file failed validation: Parse error on line 1:
            ...
            ^
            Expected one of: 'STRING', '}'

            OUTPUT;

        $this->assertSameOutput(
            $expected,
            ExitCode::FAILURE,
            DisplayNormalizer::createLoadingFilePathOutputNormalizer(),
        );
    }

    public function test_an_invalid_json_file_is_invalid_in_verbose_mode(): void
    {
        FS::dumpFile('box.json.dist', '{');

        try {
            $this->commandTester->execute(
                [
                    'command' => 'validate',
                ],
                [
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ],
            );

            self::fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $expected = <<<'OUTPUT'
                The configuration file failed validation: Parse error on line 1:
                ...
                ^
                Expected one of: 'STRING', '}'
                OUTPUT;

            self::assertSame($expected, $exception->getMessage());
            self::assertSame(0, $exception->getCode());
            self::assertNotNull($exception->getPrevious());
        }
    }

    public function test_an_incorrect_config_file_is_invalid(): void
    {
        FS::dumpFile('box.json', '{"test": true}');

        $this->commandTester->execute(
            [
                'command' => 'validate',
            ],
        );

        $expected = str_replace(
            '/path/to',
            $this->tmp,
            <<<'EOF'

                 // Loading the configuration file "box.json".

                The configuration file failed validation: "/path/to/box.json" does not match the expected JSON schema:

                  - The property test is not defined and the definition does not allow additional properties

                EOF,
        );

        $this->assertSameOutput(
            $expected,
            ExitCode::FAILURE,
            DisplayNormalizer::createLoadingFilePathOutputNormalizer(),
        );
    }

    public function test_an_incorrect_config_file_is_invalid_in_verbose_mode(): void
    {
        FS::dumpFile('box.json', '{"test": true}');

        try {
            $this->commandTester->execute(
                [
                    'command' => 'validate',
                ],
                [
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ],
            );

            self::fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                str_replace(
                    '/path/to',
                    $this->tmp,
                    <<<'EOF'
                        The configuration file failed validation: "/path/to/box.json" does not match the expected JSON schema:
                          - The property test is not defined and the definition does not allow additional properties
                        EOF,
                ),
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());
            self::assertNotNull($exception->getPrevious());
        }
    }
}
