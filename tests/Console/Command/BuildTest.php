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

use Amp\MultiReasonException;
use Amp\Parallel\Worker\TaskException;
use Assert\InvalidArgumentException as AssertInvalidArgumentException;
use DirectoryIterator;
use Generator;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use PharFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Traversable;
use function KevinGH\Box\FileSystem\mirror;

/**
 * @covers \KevinGH\Box\Console\Command\Build
 * @runTestsInSeparateProcesses This is necessary as instantiating a PHAR in memory may load/autoload some stuff which
 *                              can create undesirable side-effects.
 */
class BuildTest extends CommandTestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/build';

    public function test_it_can_build_a_PHAR_file(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);    // Set input for the passphrase
        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

? Loading the bootstrap file "/path/to/tmp/bootstrap.php"
? Removing the existing PHAR "/path/to/tmp/test.phar"
Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
? Adding main file: /path/to/tmp/run.php
? Adding binary files
    > 1 file(s)
? Adding files
    > 3 file(s)
? Generating new stub
? Setting metadata
  - array (
  'rand' => $rand,
)
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 493
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual, 'Expected logs to be identical');

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        $phar = new Phar('test.phar');

        // Check PHAR content
        $actualStub = $this->normalizeDisplay($phar->getStub());
        $expectedStub = <<<PHP
$shebang
<?php
/**
 * custom banner
 */
if (class_exists('Phar')) {
Phar::mapPhar('alias-test.phar');
require 'phar://' . __FILE__ . '/run.php';
}
__HALT_COMPILER(); ?>

PHP;

        $this->assertSame($expectedStub, $actualStub);

        $this->assertSame(
            ['rand' => $rand],
            $phar->getMetadata(),
            'Expected PHAR metadata to be set'
        );

        $expectedFiles = [
            '/one/',
            '/one/test.php',
            '/run.php',
            '/sub/',
            '/sub/test.php',
            '/test.php',
            '/two/',
            '/two/test.png',
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        $this->assertSame($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_PHAR_from_a_different_directory(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => true,
                ]
            )
        );

        chdir($this->cwd);

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);    // Set input for the passphrase
        $commandTester->execute(
            [
                'command' => 'build',
                '--working-dir' => $this->tmp,
            ],
            ['interactive' => true]
        );

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_complete_mapping(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
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

        $commandTester->setInputs(['test']);    // Set input for the passphrase
        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

? Loading the bootstrap file "/path/to/tmp/bootstrap.php"
? Removing the existing PHAR "/path/to/tmp/test.phar"
Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
  - (all) > other/
? Adding main file: /path/to/tmp/run.php
    > other/run.php
? Adding binary files
    > 1 file(s)
? Adding files
    > 3 file(s)
? Generating new stub
? Setting metadata
  - array (
  'rand' => $rand,
)
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 493
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual, 'Expected logs to be identical');

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        $phar = new Phar('test.phar');

        // Check PHAR content
        $actualStub = $this->normalizeDisplay($phar->getStub());
        $expectedStub = <<<PHP
$shebang
<?php
/**
 * custom banner
 */
if (class_exists('Phar')) {
Phar::mapPhar('alias-test.phar');
require 'phar://' . __FILE__ . '/other/run.php';
}
__HALT_COMPILER(); ?>

PHP;

        $this->assertSame($expectedStub, $actualStub);

        $this->assertSame(
            ['rand' => $rand],
            $phar->getMetadata(),
            'Expected PHAR metadata to be set'
        );

        $expectedFiles = [
            '/other/',
            '/other/one/',
            '/other/one/test.php',
            '/other/run.php',
            '/other/test.php',
            '/other/two/',
            '/other/two/test.png',
            '/sub/',
            '/sub/test.php',
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        $this->assertSame($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_PHAR_file_in_verbose_mode(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

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
                    'directories' => ['a'],
                    'files' => ['test.php'],
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

        $commandTester->setInputs(['test']);    // Set input for the passphrase
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

? Loading the bootstrap file "/path/to/tmp/bootstrap.php"
? Removing the existing PHAR "/path/to/tmp/test.phar"
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
  - (all) > other/
? Adding main file: /path/to/tmp/run.php
    > other/run.php
? Adding binary files
    > 1 file(s)
? Adding files
    > 3 file(s)
? Generating new stub
? Setting metadata
  - array (
  'rand' => $rand,
)
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 493
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_file_in_very_verbose_mode(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

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
                    'directories' => ['a'],
                    'files' => ['test.php'],
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

        $commandTester->setInputs(['test']);    // Set input for the passphrase
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

? Loading the bootstrap file "/path/to/tmp/bootstrap.php"
? Removing the existing PHAR "/path/to/tmp/test.phar"
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
  - (all) > other/
? Adding main file: /path/to/tmp/run.php
    > other/run.php
? Adding binary files
    > 1 file(s)
? Adding files
    > 3 file(s)
? Generating new stub
  - Using custom shebang line: #!__PHP_EXECUTABLE__
  - Using custom banner: custom banner
? Setting metadata
  - array (
  'rand' => $rand,
)
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 493
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $expected = str_replace(
            '__PHP_EXECUTABLE__',
            (new PhpExecutableFinder())->find(),
            $expected
        );
        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_file_in_quiet_mode(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

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
                    'directories' => ['a'],
                    'files' => ['test.php'],
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

        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual, 'Expected output logs to be identical');

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
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

    public function test_it_can_build_a_PHAR_file_using_the_PHAR_default_stub(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
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
                    'stub' => false,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);    // Set input for the passphrase
        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_file_using_a_custom_stub(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'custom_stub',
            $stub = <<<'PHP'
#!/usr/bin/php
<?php

//
// This is a custom stub: shebang & custom banner are not applied
//

if (class_exists('Phar')) {
    Phar::mapPhar('alias-test.phar');
    require 'phar://' . __FILE__ . '/other/run.php';
}
__HALT_COMPILER(); ?>

PHP
        );

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
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
                    'stub' => 'custom_stub',
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);    // Set input for the passphrase
        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        $phar = new Phar('test.phar');

        $actualStub = $this->normalizeDisplay($phar->getStub());

        $this->assertSame($stub, $actualStub);
    }

    public function test_it_cannot_build_a_PHAR_using_unreadable_files(): void
    {
        touch('test.php');
        chmod('test.php', 0000);

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'files' => ['test.php'],
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
        } catch (MultiReasonException $exception) {
            $this->assertCount(1, $exception->getReasons());

            /** @var TaskException $reason */
            $reason = $exception->getReasons()[0];

            $this->assertSame(AssertInvalidArgumentException::class, $reason->getName());
        }
    }

    public function test_it_can_build_a_PHAR_overwriting_an_existing_one_in_verbose_mode(): void
    {
        mirror(self::FIXTURES_DIR.'/dir002', $this->tmp);

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

? Removing the existing PHAR "/path/to/tmp/default.phar"
* Building the PHAR "/path/to/tmp/default.phar"
? Setting replacement values
  + @name@: world
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Adding binary files
    > No file found
? Adding files
    > 1 file(s)
? Generating new stub
? No compression
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello, world!',
            exec('php default.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_a_replacement_placeholder(): void
    {
        mirror(self::FIXTURES_DIR.'/dir001', $this->tmp);

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
? Setting replacement values
  + @name@: world
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Adding binary files
    > No file found
? Adding files
    > 1 file(s)
? Generating new stub
? No compression
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello, world!',
            exec('php default.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_a_custom_banner(): void
    {
        mirror(self::FIXTURES_DIR.'/dir003', $this->tmp);

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
? Adding main file: /path/to/tmp/test.php
? Adding binary files
    > No file found
? Adding files
    > 1 file(s)
? Generating new stub
  - Using custom banner from file: /path/to/tmp/banner
? No compression
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_a_stub_file(): void
    {
        mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

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
? Adding main file: /path/to/tmp/test.php
? Adding binary files
    > No file found
? Adding files
    > 1 file(s)
? Using stub file: /path/to/tmp/stub.php
? No compression
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_the_default_stub_file(): void
    {
        mirror(self::FIXTURES_DIR.'/dir005', $this->tmp);

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
? Adding binary files
    > No file found
? Adding files
    > 1 file(s)
? Using default stub
? No compression
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_with_compressed_code(): void
    {
        mirror(self::FIXTURES_DIR.'/dir006', $this->tmp);

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
? Adding main file: /path/to/tmp/test.php
? Adding binary files
    > No file found
? Adding files
    > 1 file(s)
? Generating new stub
? Compressing with the algorithm "GZ"
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $builtPhar = new Phar('test.phar');

        $this->assertFalse($builtPhar->isCompressed()); // TODO: this is a bug, see https://github.com/humbug/box/issues/20
        $this->assertTrue($builtPhar['test.php']->isCompressed());

        $this->assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected the PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_in_a_non_existent_directory(): void
    {
        mirror(self::FIXTURES_DIR.'/dir007', $this->tmp);

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

* Building the PHAR "/path/to/tmp/foo/bar/test.phar"
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Adding binary files
    > No file found
? Adding files
    > 1 file(s)
? Generating new stub
? No compression
* Done.

 // PHAR size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php foo/bar/test.phar'),
            'Expected the PHAR to be executable'
        );
    }

    /**
     * @dataProvider provideAliasConfig
     */
    public function test_it_configures_the_PHAR_alias(bool $stub, bool $web): void
    {
        mirror(self::FIXTURES_DIR.'/dir008', $this->tmp);

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => $alias = 'alias-test.phar',
                    'main' => 'index.php',
                    'stub' => $stub,
                    'web' => $web,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $this->assertSame(
            0,
            $commandTester->getStatusCode(),
            sprintf(
                'Expected the command to successfully run. Got: %s',
                $this->normalizeDisplay($commandTester->getDisplay(true))
            )
        );

        $this->assertSame(
            '',
            exec('php default.phar'),
            'Expected PHAR to be executable'
        );

        $phar = new Phar('default.phar');

        // Check the stub content
        $actualStub = $this->normalizeStub($phar->getStub());
        $defaultStub = $this->normalizeStub(file_get_contents(self::FIXTURES_DIR.'/../default_stub.php'));

        if ($web) {
            if ($stub) {
                $this->assertSame($phar->getPath(), $phar->getAlias());

                $this->assertRegExp(
                    '/Phar::webPhar\(\'alias-test\.phar\', "index\.php"\);/',
                    $actualStub
                );
                $this->assertNotRegExp(
                    '/Phar::mapPhar\(.*\);/',
                    $actualStub
                );
            } else {
                $this->assertSame($alias, $phar->getAlias());

                $this->assertSame($defaultStub, $actualStub);

                // No alias is found: I find it weird but well, that's the default stub so there is not much that can
                // be done here. Maybe there is a valid reason I'm not aware of.
                $this->assertNotRegExp(
                    '/alias-test\.phar/',
                    $actualStub
                );
            }
        } else {
            if ($stub) {
                $this->assertSame($phar->getPath(), $phar->getAlias());

                $this->assertNotRegExp(
                    '/Phar::webPhar\(.*\);/',
                    $actualStub
                );
                $this->assertRegExp(
                    '/Phar::mapPhar\(\'alias-test\.phar\'\);/',
                    $actualStub
                );
            } else {
                $this->assertSame($alias, $phar->getAlias());

                $this->assertSame($defaultStub, $actualStub);

                // No alias is found: I find it weird but well, that's the default stub so there is not much that can
                // be done here. Maybe there is a valid reason I'm not aware of.
                $this->assertNotRegExp(
                    '/alias-test\.phar/',
                    $actualStub
                );
            }
        }

        $expectedFiles = [
            '/index.php',
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        $this->assertSame($expectedFiles, $actualFiles);
    }

    public function provideAliasConfig(): Generator
    {
        $values = [true, false];

        foreach ($values as $value0) {
            foreach ($values as $value1) {
                yield [$value0, $value1];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Build();
    }

    private function normalizeDisplay(string $display)
    {
        $display = str_replace($this->tmp, '/path/to/tmp', $display);

        $display = preg_replace(
            '/\/\/ PHAR size: \d+\.\d{2}K?B/',
            '// PHAR size: 100B',
            $display
        );

        $display = preg_replace(
            '/\/\/ Memory usage: \d+\.\d{2}MB \(peak: \d+\.\d{2}MB\), time: \d+\.\d{2}s/',
            '// Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s',
            $display
        );

        $lines = explode("\n", $display);

        $lines = array_map(
            'rtrim',
            $lines
        );

        return implode("\n", $lines);
    }

    private function retrievePharFiles(Phar $phar, Traversable $traversable = null): array
    {
        $root = 'phar://'.str_replace('\\', '/', realpath($phar->getPath())).'/';

        if (null === $traversable) {
            $traversable = $phar;
        }

        $paths = [];

        foreach ($traversable as $fileInfo) {
            /** @var PharFileInfo $fileInfo */
            $fileInfo = $phar[str_replace($root, '', $fileInfo->getPathname())];

            $path = substr($fileInfo->getPathname(), strlen($root) - 1);

            if ($fileInfo->isDir()) {
                $path .= '/';

                $paths = array_merge(
                    $paths,
                    $this->retrievePharFiles(
                        $phar,
                        new DirectoryIterator($fileInfo->getPathname())
                    )
                );
            }

            $paths[] = $path;
        }

        sort($paths);

        return array_unique($paths);
    }

    private function normalizeStub(string $stub): string
    {
        return implode(
            "\n",
            array_map(
                'trim',
                preg_split('/\n/', $stub)
            )
        );
    }
}
