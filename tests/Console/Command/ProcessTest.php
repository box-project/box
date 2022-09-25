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
use KevinGH\Box\Console\DisplayNormalizer;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\touch;
use KevinGH\Box\Test\CommandTestCase;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \KevinGH\Box\Console\Command\Process
 */
class ProcessTest extends CommandTestCase
{
    protected function getCommand(): Command
    {
        return new Process();
    }

    public function test_it_processes_a_file_and_displays_the_processed_contents_with_no_config(): void
    {
        dump_file('index.php', '');

        $this->commandTester->execute(
            [
                'command' => 'process',
                'file' => 'index.php',
            ],
        );

        $expectedPath = $this->tmp.'/index.php';

        $expected = <<<OUTPUT


             // Loading without a configuration file.

            ⚡  Processing the contents of the file $expectedPath

            No replacement values registered

            No compactor registered

            Processed contents:

            """

            """

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_processes_a_file_and_displays_the_processed_contents_with_a_config(): void
    {
        touch('index.php');
        dump_file(
            'acme.json',
            <<<'JSON'
                {
                    "foo": "@foo@"
                }
                JSON,
        );
        dump_file(
            'box.json',
            <<<'JSON'
                {
                    "replacements": {
                        "foo": "bar"
                    },
                    "compactors": [
                        "KevinGH\\Box\\Compactor\\Json"
                    ]
                }
                JSON,
        );

        $this->commandTester->execute(
            [
                'command' => 'process',
                'file' => 'acme.json',
            ],
        );

        $expectedFilePath = $this->tmp.'/acme.json';

        $expected = <<<OUTPUT


             // Loading the configuration file "box.json".

            ⚡  Processing the contents of the file $expectedFilePath

            Registered replacement values:
              + @foo@: bar

            Registered compactors:
              + KevinGH\Box\Compactor\Json

            Processed contents:

            """
            {"foo":"bar"}
            """

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_processes_the_file_relative_to_the_config_base_path(): void
    {
        dump_file(
            'index.php',
            <<<'PHP'
                <?php

                echo 'Hello world!';
                PHP,
        );

        dump_file(
            'box.json',
            <<<'JSON'
                {
                    "replacements": {
                        "foo": "bar"
                    },
                    "compactors": [
                        "KevinGH\\Box\\Compactor\\PhpScoper"
                    ]
                }
                JSON,
        );
        dump_file(
            'scoper.inc.php',
            <<<'PHP'
                <?php

                return [
                    'prefix' => '_Prefix',
                    'patchers' => [
                        function (string $filePath, string $prefix, string $contents): string {
                            if ('index.php' !== $filePath) {
                                return $contents;
                            }

                            return str_replace('Hello world!', '!dlrow olleH', $contents);
                        },
                    ],
                ];

                PHP,
        );

        $this->commandTester->execute(
            [
                'command' => 'process',
                'file' => $this->tmp.'/index.php',
            ],
        );

        $expectedPath = $this->tmp.'/index.php';

        $expected = <<<OUTPUT


             // Loading the configuration file "box.json".

            ⚡  Processing the contents of the file $expectedPath

            Registered replacement values:
              + @foo@: bar

            Registered compactors:
              + KevinGH\Box\Compactor\PhpScoper

            Processed contents:

            """
            <?php

            namespace _Prefix;

            echo '!dlrow olleH';

            """

            Whitelist:

            """
            Humbug\PhpScoper\Symbol\SymbolsRegistry {#140
              -recordedFunctions: []
              -recordedClasses: []
            }

            """

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_processes_a_file_and_displays_only_the_processed_contents_in_quiet_mode(): void
    {
        touch('index.php');
        dump_file(
            'acme.json',
            <<<'JSON'
                {
                    "foo": "@foo@"
                }
                JSON,
        );
        dump_file(
            'box.json',
            <<<'JSON'
                {
                    "replacements": {
                        "foo": "bar"
                    },
                    "compactors": [
                        "KevinGH\\Box\\Compactor\\Json"
                    ]
                }
                JSON,
        );

        $this->commandTester->execute(
            [
                'command' => 'process',
                'file' => 'acme.json',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_QUIET],
        );

        $expected = <<<'OUTPUT'
            {"foo":"bar"}

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function assertSameOutput(
        string $expectedOutput,
        int $expectedStatusCode,
        callable ...$extraNormalizers,
    ): void {
        parent::assertSameOutput(
            $expectedOutput,
            $expectedStatusCode,
            DisplayNormalizer::createVarDumperObjectReferenceNormalizer(),
            DisplayNormalizer::createLoadingFilePathOutputNormalizer(),
            ...$extraNormalizers,
        );
    }
}
