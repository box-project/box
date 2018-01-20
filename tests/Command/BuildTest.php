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

use InvalidArgumentException;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * @covers \KevinGH\Box\Command\Build
 */
class BuildTest extends CommandTestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/build';

    public function test_it_can_build_a_PHAR_file(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

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

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $expected = <<<'OUTPUT'

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    

Box (repo)

Building the PHAR "/path/to/tmp/test.phar"
? Adding directories.
? Adding files.
Private key passphrase:
* Done.

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
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

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

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    

Box (repo)

? Loading the bootstrap file "/path/to/tmp/bootstrap.php".
? Removing the existing PHAR "/path/to/tmp/test.phar".
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths.
  - a/deep/test/directory > sub
  - (all) > other/
? Adding finder files.
? Adding binary finder files.
? Adding directories.
? Adding files.
? Adding main file: /path/to/tmp/run.php
    > other/run.php
? Generating new stub.
? Setting metadata.
? Signing using a private key.
Private key passphrase:
? Setting file permissions.
* Done.

OUTPUT;

        $expected = str_replace('/path/to/tmp', $this->tmp, $expected);
        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_file_in_very_verbose_mode(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

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

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    

Box (repo)

? Loading the bootstrap file "/path/to/tmp/bootstrap.php".
? Removing the existing PHAR "/path/to/tmp/test.phar".
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths.
  - a/deep/test/directory > sub
  - (all) > other/
? Adding finder files.
    > other/one/test.php
? Adding binary finder files.
    > other/two/test.png
? Adding directories.
    > sub/test.php
? Adding files.
    > other/test.php
? Adding main file: /path/to/tmp/run.php
    > other/run.php
? Generating new stub.
  - Using custom shebang line: #!__PHP_EXECUTABLE__
  - Using custom banner: custom banner
? Setting metadata.
? Signing using a private key.
Private key passphrase:
? Setting file permissions.
* Done.

OUTPUT;

        $expected = str_replace(
            ['/path/to/tmp', '__PHP_EXECUTABLE__'],
            [$this->tmp, (new PhpExecutableFinder())->find()],
            $expected
        );
        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_file_in_quiet_mode(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

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

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_QUIET,
            ]
        );

        $expected = '';

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
                [
                    'interactive' => false,
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertTrue(true);
        }
    }

    public function test_it_can_build_a_PHAR_overwriting_an_existing_one_in_verbose_mode(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir002', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    

Box (repo)

? Removing the existing PHAR "/path/to/tmp/default.phar".
* Building the PHAR "/path/to/tmp/default.phar"
? Setting replacement values.
  + @name@: world
? No compactor to register
? Adding files.
? Adding main file: /path/to/tmp/test.php
? Generating new stub.
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

    public function test_it_can_build_a_PHAR_with_a_replacement_placeholder(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir001', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    

Box (repo)

* Building the PHAR "/path/to/tmp/default.phar"
? Setting replacement values.
  + @name@: world
? No compactor to register
? Adding files.
? Adding main file: /path/to/tmp/test.php
? Generating new stub.
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

    public function test_it_can_build_a_PHAR_with_a_custom_banner(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir003', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            [
                'command' => 'build',
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    

Box (repo)

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding files.
  + /path/to/tmp/test.php
? Adding main file: /path/to/tmp/test.php
? Generating new stub.
  - Using custom banner from file: /path/to/tmp/banner
* Done.

OUTPUT;

        $expected = str_replace('/path/to/tmp', $this->tmp, $expected);
        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php test.phar')
        );
    }

    public function test_it_can_build_a_PHAR_with_a_stub_file(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    

Box (repo)

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding files.
? Adding main file: /path/to/tmp/test.php
? Using stub file: /path/to/tmp/stub.php
* Done.

OUTPUT;

        $expected = str_replace('/path/to/tmp', $this->tmp, $expected);
        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php test.phar')
        );
    }

    public function test_it_can_build_a_PHAR_with_the_default_stub_file(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir005', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    

Box (repo)

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding files.
? Using default stub.
* Done.

OUTPUT;

        $expected = str_replace('/path/to/tmp', $this->tmp, $expected);
        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_with_compressed_code(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir006', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    

Box (repo)

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding files.
? Adding main file: /path/to/tmp/test.php
? Generating new stub.
? Compressing.
* Done.

OUTPUT;

        $expected = str_replace('/path/to/tmp', $this->tmp, $expected);
        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php test.phar')
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Build();
    }
}
