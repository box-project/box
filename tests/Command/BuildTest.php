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

namespace KevinGH\Box\Command;

use Herrera\Box\Compactor\Php;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * @covers \KevinGH\Box\Command\Build
 */
class BuildTest extends CommandTestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/build';

    public function test_it_can_build_a_PHAR_file(): void
    {
        (new Filesystem())->mirror(self::FIXTURES.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => 'a',
                    'files' => 'test.php',
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                        ['' => 'other/'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();
        $commandTester->execute(['command' => 'build']);

        $expected = <<<'OUTPUT'
Building...

OUTPUT;

        $expected = str_replace('/path/to/tmp', $this->tmp, $expected);
        $actual = $commandTester->getDisplay(true);

        // Check output logs
        $this->assertSame($expected, $actual);

        // Check PHAR execution output
        $this->assertSame(
            'Hello, world!',
            exec('php test.phar')
        );

        // Check PHAR content
        $pharContents = file_get_contents('test.phar');
        $shebang = preg_quote($shebang, '/');

        $this->assertSame(
            1,
            preg_match(
                "/$shebang/",
                $pharContents
            )
        );
        $this->assertSame(
            1,
            preg_match(
                '/custom banner/',
                $pharContents
            )
        );

        $phar = new Phar('test.phar');

        $this->assertSame(['rand' => $rand], $phar->getMetadata());
    }

    public function test_it_can_build_a_PHAR_file_in_verbose_mode(): void
    {
        (new Filesystem())->mirror(self::FIXTURES.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => 'a',
                    'files' => 'test.php',
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                        ['' => 'other/'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'build'],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]
        );

        $expected = <<<OUTPUT
? Loading bootstrap file: /path/to/tmp/bootstrap.php
? Removing previously built Phar...
* Building...
? Output path: /path/to/tmp/test.phar
? Registering compactors...
  + Herrera\Box\Compactor\Php
? Mapping paths:
  - a/deep/test/directory > sub
  - (all) > other/
? Adding Finder files...
  + /path/to/tmp/one/test.php
    > other/one/test.php
? Adding binary Finder files...
  + /path/to/tmp/two/test.png
    > other/two/test.png
? Adding directories...
  + /path/to/tmp/a/deep/test/directory/test.php
    > sub/test.php
? Adding files...
  + /path/to/tmp/test.php
    > other/test.php
? Adding main file: /path/to/tmp/run.php
    > other/run.php
? Generating new stub...
  - Using custom shebang line: $shebang
  - Using custom banner.
? Setting metadata...
? Signing using a private key...
? Setting file permissions...
* Done.

OUTPUT;

        $expected = str_replace('/path/to/tmp', $this->tmp, $expected);
        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual);
    }

    public function test_it_cannot_build_a_PHAR_using_unreadable_files(): void
    {
        touch('test.php');
        chmod('test.php', 0000);

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'files' => 'test.php',
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        try {
            $commandTester->execute(
                ['command' => 'build'],
                ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                sprintf(
                    'The file "%s/test.php" is not readable.',
                    $this->tmp
                ),
                $exception->getMessage()
            );
        }
    }

    public function test_it_can_build_a_PHAR_with_a_replacement_placeholder(): void
    {
        (new Filesystem())->mirror(self::FIXTURES.'/dir001', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(['command' => 'build']);

        $expected = <<<'OUTPUT'
Building...

OUTPUT;

        $expected = str_replace('/path/to/tmp', $this->tmp, $expected);
        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello, world!',
            exec('php default.phar')
        );
    }

    public function test_it_can_build_a_PHAR_overwriting_an_existing_one_in_verbose_mode(): void
    {
        (new Filesystem())->mirror(self::FIXTURES.'/dir001', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'build'],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]
        );

        $expected = <<<'OUTPUT'
* Building...
? Output path: /path/to/tmp/default.phar
? Setting replacement values...
  + @name@: world
? Adding files...
  + /path/to/tmp/test.php
? Adding main file: /path/to/tmp/test.php
? Generating new stub...
* Done.

OUTPUT;

        $expected = str_replace('/path/to/tmp', $this->tmp, $expected);
        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello, world!',
            exec('php default.phar')
        );
    }

    public function testBuildStubBannerFile(): void
    {
        file_put_contents('banner', 'custom banner');
        file_put_contents('test.php', '<?php echo "Hello!";');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner-file' => 'banner',
                    'files' => 'test.php',
                    'main' => 'test.php',
                    'output' => 'test.phar',
                    'stub' => true,
                ]
            )
        );

        $tester = $this->getCommandTester();
        $tester->execute(
            [
                'command' => 'build',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->tmp.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Adding main file: {$dir}test.php
? Generating new stub...
  - Using custom banner from file: {$dir}banner
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $this->assertSame(
            'Hello!',
            exec('php test.phar')
        );
    }

    public function testBuildStubFile(): void
    {
        touch('test.php');
        file_put_contents('stub.php', '<?php echo "Hello!"; __HALT_COMPILER();');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'files' => 'test.php',
                    'output' => 'test.phar',
                    'stub' => 'stub.php',
                ]
            )
        );

        $tester = $this->getCommandTester();
        $tester->execute(
            [
                'command' => 'build',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->tmp.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Using stub file: {$dir}stub.php
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testBuildDefaultStub(): void
    {
        touch('test.php');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'files' => 'test.php',
                    'output' => 'test.phar',
                ]
            )
        );

        $tester = $this->getCommandTester();
        $tester->execute(
            [
                'command' => 'build',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->tmp.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Using default stub.
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testBuildCompressed(): void
    {
        file_put_contents('test.php', '<?php echo "Hello!";');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'compression' => 'GZ',
                    'files' => 'test.php',
                    'main' => 'test.php',
                    'output' => 'test.phar',
                    'stub' => true,
                ]
            )
        );

        $tester = $this->getCommandTester();
        $tester->execute(
            [
                'command' => 'build',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->tmp.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Adding main file: {$dir}test.php
? Generating new stub...
? Compressing...
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $this->assertSame(
            'Hello!',
            exec('php test.phar')
        );
    }

    public function testBuildQuiet(): void
    {
        mkdir('one');
        file_put_contents('one/test.php', '<?php echo "Hello!";');
        file_put_contents('run.php', '<?php require "one/test.php";');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'finder' => [['in' => 'one']],
                    'main' => 'run.php',
                    'output' => 'test.phar',
                    'stub' => true,
                ]
            )
        );

        $tester = $this->getCommandTester();
        $tester->execute(['command' => 'build']);

        $this->assertSame("Building...\n", $this->getOutput($tester));
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Build();
    }
}
