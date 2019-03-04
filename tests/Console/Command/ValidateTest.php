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

use KevinGH\Box\Console\DisplayNormalizer;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\touch;
use KevinGH\Box\Test\CommandTestCase;
use function preg_replace;
use RuntimeException;
use function str_replace;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \KevinGH\Box\Console\Command\Validate
 * @covers \KevinGH\Box\Console\MessageRenderer
 * @runTestsInSeparateProcesses
 */
class ValidateTest extends CommandTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Validate();
    }

    public function test_it_validates_a_given_file(): void
    {
        touch('index.php');
        dump_file('test.json', '{}');

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

 // Loading the configuration file "test.json".

No recommendation found.
No warning found.

 [OK] The configuration file passed the validation.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_reports_the_recommendations_found(): void
    {
        touch('index.php');
        dump_file(
            'test.json',
            <<<'JSON'
{
    "key": null
}
JSON
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

 // Loading the configuration file "test.json".

💡  1 recommendation found:
    - The setting "key" has been set but is unnecessary since the signing algorithm is not "OPENSSL".
No warning found.

 ! [CAUTION] The configuration file passed the validation with recommendations.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_it_does_not_fail_when_recommendations_are_found_but_ignore_message_is_passed(): void
    {
        touch('index.php');
        dump_file(
            'test.json',
            <<<'JSON'
{
    "key": null
}
JSON
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
                '--ignore-recommendations-and-warnings' => null,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

 // Loading the configuration file "test.json".

💡  1 recommendation found:
    - The setting "key" has been set but is unnecessary since the signing algorithm is not "OPENSSL".
No warning found.

 ! [CAUTION] The configuration file passed the validation with recommendations.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_reports_the_warnings_found(): void
    {
        touch('index.php');
        dump_file(
            'test.json',
            <<<'JSON'
{
    "key": "key-file"
}
JSON
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

 // Loading the configuration file "test.json".

No recommendation found.
⚠️  1 warning found:
    - The setting "key" has been set but is ignored since the signing algorithm is not "OPENSSL".

 ! [CAUTION] The configuration file passed the validation with warnings.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_it_does_not_fail_when_warnings_are_found_but_ignore_message_is_passed(): void
    {
        touch('index.php');
        dump_file(
            'test.json',
            <<<'JSON'
{
    "key": "key-file"
}
JSON
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
                '--ignore-recommendations-and-warnings' => null,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

 // Loading the configuration file "test.json".

No recommendation found.
⚠️  1 warning found:
    - The setting "key" has been set but is ignored since the signing algorithm is not "OPENSSL".

 ! [CAUTION] The configuration file passed the validation with warnings.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_reports_the_recommendations_and_warnings_found(): void
    {
        touch('index.php');
        dump_file(
            'test.json',
            <<<'JSON'
{
    "check-requirements": true
}
JSON
        );

        $this->commandTester->execute(
            [
                'command' => 'validate',
                'file' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

 // Loading the configuration file "test.json".

💡  1 recommendation found:
    - The "check-requirements" setting can be omitted since is set to its default value
⚠️  1 warning found:
    - The requirement checker could not be used because the composer.json and composer.lock file could not be found.

 ! [CAUTION] The configuration file passed the validation with recommendations
 !           and warnings.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(1, $this->commandTester->getStatusCode());
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
        dump_file('box.json', '{');

        $this->commandTester->execute(
            [
                'command' => 'validate',
            ]
        );

        $expected = <<<'OUTPUT'

 // Loading the configuration file "box.json".

The configuration file failed validation: Parse error on line 1:
{
^
Expected one of: 'STRING', '}'

OUTPUT;

        $actual = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));

        $actual = preg_replace(
            '/\s\/\/ Loading the configuration file([\s\S]*)box\.json[comment\<\>\n\s\/]*"\./',
            ' // Loading the configuration file "box.json".',
            $actual
        );

        $this->assertSame($expected, $actual);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_an_invalid_JSON_file_is_invalid_in_verbose_mode(): void
    {
        dump_file('box.json.dist', '{');

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
        dump_file('box.json', '{"test": true}');

        $this->commandTester->execute(
            [
                'command' => 'validate',
            ]
        );

        $expected = str_replace(
            '/path/to',
            $this->tmp,
            <<<'EOF'

 // Loading the configuration file "box.json".

The configuration file failed validation: "/path/to/box.json" does not match the expected JSON schema:

  - The property test is not defined and the definition does not allow additional properties

EOF
        );

        $actual = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));

        $actual = preg_replace(
            '/\s\/\/ Loading the configuration file([\s\S]*)box\.json[comment\<\>\n\s\/]*"\./',
            ' // Loading the configuration file "box.json".',
            $actual
        );

        $this->assertSame($expected, $actual);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_an_incorrect_config_file_is_invalid_in_verbose_mode(): void
    {
        dump_file('box.json', '{"test": true}');

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
}
