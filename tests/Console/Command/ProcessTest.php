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
use KevinGH\Box\Test\CommandTestCase;
use Symfony\Component\Console\Command\Command;
use function KevinGH\Box\FileSystem\dump_file;
use function preg_match;
use function preg_replace;
use function str_replace;

/**
 * @covers \KevinGH\Box\Console\Command\Process
 */
class ProcessTest extends CommandTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Process();
    }

    public function test_it_process_a_file_and_displays_the_processed_contents_with_no_config(): void
    {
        dump_file('index.php', '');

        $this->commandTester->execute(
            [
                'command' => 'process',
                'file' => 'index.php',
            ]
        );
        $actual = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));

        $expectedPath = $this->tmp.'/index.php';

        $expected = <<<OUTPUT


 // Loading without a configuration file.

Processing the contents of the file $expectedPath

No replacement values registered

No compactor registered

Processed contents:

"""

"""

OUTPUT;

        $this->assertSame($expected, $actual);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_process_a_file_and_displays_the_processed_contents_with_a_config(): void
    {
        touch('index.php');
        dump_file(
            'acme.json',
            <<<'JSON'
{
    "foo": "@foo@"
}
JSON
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
JSON
        );

        $this->commandTester->execute(
            [
                'command' => 'process',
                'file' => 'acme.json',
            ]
        );
        $actual = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));

        if (1 === preg_match('/\/\/ Loading the configuration file\n \/\/ "(?<file>[\s\S]*?)"./', $actual, $matches)) {
            $file = $matches['file'];

            $actual = str_replace(
                $file,
                preg_replace('/\n \/\/ /', '', $file),
                $actual
            );

            DisplayNormalizer::removeMiddleStringLineReturns($actual);
        }

        $expectedConfigPath = $this->tmp.'/box.json';
        $expectedFilePath = $this->tmp.'/acme.json';

        $expected = <<<OUTPUT


 // Loading the configuration file
 // "$expectedConfigPath".

Processing the contents of the file $expectedFilePath

Registered replacement values:
  + @foo@: bar

Registered compactors:
  + KevinGH\Box\Compactor\Json

Processed contents:

"""
{"foo":"bar"}
"""

OUTPUT;

        $this->assertSame($expected, $actual);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}
