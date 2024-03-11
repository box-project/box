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
use KevinGH\Box\Test\CommandTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[CoversClass(ProcessCommand::class)]
class ProcessCommandTest extends CommandTestCase
{
    protected function getCommand(): Command
    {
        return new ProcessCommand();
    }

    public function test_it_processes_a_file_and_displays_the_processed_contents_with_no_config(): void
    {
        FS::dumpFile('index.php');

        $this->commandTester->execute(
            [
                'command' => 'process',
                'file' => 'index.php',
            ],
        );

        $expectedPath = $this->tmp.'/index.php';

        $expected = <<<OUTPUT


             // Loading without a configuration file.

            ⚡  Processing the contents of the file {$expectedPath}

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
        FS::touch('index.php');
        FS::dumpFile(
            'acme.json',
            <<<'JSON'
                {
                    "foo": "@foo@"
                }
                JSON,
        );
        FS::dumpFile(
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

            ⚡  Processing the contents of the file {$expectedFilePath}

            Registered replacement values:
              + @foo@: bar

            Registered compactors:
              + KevinGH\\Box\\Compactor\\Json

            Processed contents:

            """
            {"foo":"bar"}
            """

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_processes_the_file_relative_to_the_config_base_path(): void
    {
        FS::dumpFile(
            'index.php',
            <<<'PHP'
                <?php

                echo 'Hello world!';
                PHP,
        );

        FS::dumpFile(
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
        FS::dumpFile(
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

            ⚡  Processing the contents of the file {$expectedPath}

            Registered replacement values:
              + @foo@: bar

            Registered compactors:
              + KevinGH\\Box\\Compactor\\PhpScoper

            Processed contents:

            """
            <?php

            namespace _Prefix;

            echo '!dlrow olleH';

            """

            Symbols Registry:

            """
            Humbug\\PhpScoper\\Symbol\\SymbolsRegistry {#140
              -recordedFunctions: []
              -recordedClasses: []
            }

            """

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_processes_a_file_and_displays_only_the_processed_contents_in_quiet_mode(): void
    {
        FS::touch('index.php');
        FS::dumpFile(
            'acme.json',
            <<<'JSON'
                {
                    "foo": "@foo@"
                }
                JSON,
        );
        FS::dumpFile(
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
