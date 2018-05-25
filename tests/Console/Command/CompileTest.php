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

use DirectoryIterator;
use function extension_loaded;
use Generator;
use InvalidArgumentException;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Console\DisplayNormalizer;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use PharFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Traversable;
use function file_get_contents;
use function file_put_contents;
use function iterator_to_array;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\mirror;
use function KevinGH\Box\FileSystem\rename;
use function preg_match;
use function preg_replace;
use function sort;
use function sprintf;
use function str_replace;

/**
 * @covers \KevinGH\Box\Console\Command\Compile
 * @runTestsInSeparateProcesses This is necessary as instantiating a PHAR in memory may load/autoload some stuff which
 *                              can create undesirable side-effects.
 */
class CompileTest extends CommandTestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/build';

    public function test_it_can_build_a_PHAR_file(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        file_put_contents('composer.json', '{}');
        file_put_contents('composer.lock', '{}');

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
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
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)


 // Loading the configuration file "/path/to/box.json.dist".

? Removing the existing PHAR "/path/to/tmp/test.phar"
Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
? Adding main file: /path/to/tmp/run.php
? Adding requirements checker
? Adding binary files
    > 1 file(s)
? Adding files
    > 5 file(s)
? Generating new stub
  - Using shebang line: $shebang
  - Using banner:
    > custom banner
? Setting metadata
  - array (
  'rand' => $rand,
)
? Dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 0755
* Done.

 // PHAR: 44 files (100B)
 // You can inspect the generated PHAR with the "info" command.

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

/*
 * custom banner
 */

Phar::mapPhar('alias-test.phar');

require 'phar://alias-test.phar/.box/check_requirements.php';

require 'phar://alias-test.phar/run.php';

__HALT_COMPILER(); ?>

PHP;

        $this->assertSame($expectedStub, $actualStub);

        $this->assertSame(
            ['rand' => $rand],
            $phar->getMetadata(),
            'Expected PHAR metadata to be set'
        );

        $expectedFiles = [
            '/.box/',
            '/.box/.requirements.php',
            '/.box/bin/',
            '/.box/bin/check-requirements.php',
            '/.box/check_requirements.php',
            '/.box/composer.json',
            '/.box/composer.lock',
            '/.box/src/',
            '/.box/src/Checker.php',
            '/.box/src/IO.php',
            '/.box/src/IsExtensionFulfilled.php',
            '/.box/src/IsFulfilled.php',
            '/.box/src/IsPhpVersionFulfilled.php',
            '/.box/src/Printer.php',
            '/.box/src/Requirement.php',
            '/.box/src/RequirementCollection.php',
            '/.box/src/Terminal.php',
            '/.box/vendor/',
            '/.box/vendor/autoload.php',
            '/.box/vendor/composer/',
            '/.box/vendor/composer/ClassLoader.php',
            '/.box/vendor/composer/LICENSE',
            '/.box/vendor/composer/autoload_classmap.php',
            '/.box/vendor/composer/autoload_namespaces.php',
            '/.box/vendor/composer/autoload_psr4.php',
            '/.box/vendor/composer/autoload_real.php',
            '/.box/vendor/composer/autoload_static.php',
            '/.box/vendor/composer/installed.json',
            '/.box/vendor/composer/semver/',
            '/.box/vendor/composer/semver/src/',
            '/.box/vendor/composer/semver/src/Comparator.php',
            '/.box/vendor/composer/semver/src/Constraint/',
            '/.box/vendor/composer/semver/src/Constraint/AbstractConstraint.php',
            '/.box/vendor/composer/semver/src/Constraint/Constraint.php',
            '/.box/vendor/composer/semver/src/Constraint/ConstraintInterface.php',
            '/.box/vendor/composer/semver/src/Constraint/EmptyConstraint.php',
            '/.box/vendor/composer/semver/src/Constraint/MultiConstraint.php',
            '/.box/vendor/composer/semver/src/Semver.php',
            '/.box/vendor/composer/semver/src/VersionParser.php',
            '/one/',
            '/one/test.php',
            '/run.php',
            '/sub/',
            '/sub/test.php',
            '/test.php',
            '/two/',
            '/two/test.png',
            '/vendor/',
            '/vendor/autoload.php',
            '/vendor/composer/',
            '/vendor/composer/ClassLoader.php',
            '/vendor/composer/LICENSE',
            '/vendor/composer/autoload_classmap.php',
            '/vendor/composer/autoload_namespaces.php',
            '/vendor/composer/autoload_psr4.php',
            '/vendor/composer/autoload_real.php',
            '/vendor/composer/autoload_static.php',
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
                'command' => 'compile',
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

    public function test_it_can_build_a_PHAR_without_any_configuration(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        rename('run.php', 'index.php');

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

Building the PHAR "/path/to/tmp/index.phar"
? No compactor to register
? Adding main file: /path/to/tmp/index.php
? Adding requirements checker
? Adding binary files
    > No file found
? Adding files
    > 9 file(s)
? Generating new stub
  - Using shebang line: #!/usr/bin/env php
  - Using banner:
    > Generated by Humbug Box.
    >
    > @link https://github.com/humbug/box
? Dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
* Done.

 // PHAR: 48 files (100B)
 // You can inspect the generated PHAR with the "info" command.

 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual, 'Expected logs to be identical');

        $this->assertSame(
            'Hello, world!',
            exec('php index.phar'),
            'Expected PHAR to be executable'
        );

        $phar = new Phar('index.phar');

        // Check PHAR content
        $actualStub = preg_replace(
            '/box-auto-generated-alias-[\da-zA-Z]{13}\.phar/',
            'box-auto-generated-alias-__uniqid__.phar',
            $this->normalizeDisplay($phar->getStub())
        );

        $expectedStub = <<<'PHP'
#!/usr/bin/env php
<?php

/*
 * Generated by Humbug Box.
 *
 * @link https://github.com/humbug/box
 */

Phar::mapPhar('box-auto-generated-alias-__uniqid__.phar');

require 'phar://box-auto-generated-alias-__uniqid__.phar/.box/check_requirements.php';

require 'phar://box-auto-generated-alias-__uniqid__.phar/index.php';

__HALT_COMPILER(); ?>

PHP;

        $this->assertSame($expectedStub, $actualStub);

        $this->assertNull(
            $phar->getMetadata(),
            'Expected PHAR metadata to be set'
        );

        $expectedFiles = [
            '/.box/',
            '/.box/.requirements.php',
            '/.box/bin/',
            '/.box/bin/check-requirements.php',
            '/.box/check_requirements.php',
            '/.box/composer.json',
            '/.box/composer.lock',
            '/.box/src/',
            '/.box/src/Checker.php',
            '/.box/src/IO.php',
            '/.box/src/IsExtensionFulfilled.php',
            '/.box/src/IsFulfilled.php',
            '/.box/src/IsPhpVersionFulfilled.php',
            '/.box/src/Printer.php',
            '/.box/src/Requirement.php',
            '/.box/src/RequirementCollection.php',
            '/.box/src/Terminal.php',
            '/.box/vendor/',
            '/.box/vendor/autoload.php',
            '/.box/vendor/composer/',
            '/.box/vendor/composer/ClassLoader.php',
            '/.box/vendor/composer/LICENSE',
            '/.box/vendor/composer/autoload_classmap.php',
            '/.box/vendor/composer/autoload_namespaces.php',
            '/.box/vendor/composer/autoload_psr4.php',
            '/.box/vendor/composer/autoload_real.php',
            '/.box/vendor/composer/autoload_static.php',
            '/.box/vendor/composer/installed.json',
            '/.box/vendor/composer/semver/',
            '/.box/vendor/composer/semver/src/',
            '/.box/vendor/composer/semver/src/Comparator.php',
            '/.box/vendor/composer/semver/src/Constraint/',
            '/.box/vendor/composer/semver/src/Constraint/AbstractConstraint.php',
            '/.box/vendor/composer/semver/src/Constraint/Constraint.php',
            '/.box/vendor/composer/semver/src/Constraint/ConstraintInterface.php',
            '/.box/vendor/composer/semver/src/Constraint/EmptyConstraint.php',
            '/.box/vendor/composer/semver/src/Constraint/MultiConstraint.php',
            '/.box/vendor/composer/semver/src/Semver.php',
            '/.box/vendor/composer/semver/src/VersionParser.php',
            '/binary',
            '/bootstrap.php',
            '/index.php',
            '/one/',
            '/one/test.php',
            '/private.key',
            '/test.phar',
            '/test.phar.pubkey',
            '/test.php',
            '/two/',
            '/two/test.png',
            '/vendor/',
            '/vendor/autoload.php',
            '/vendor/composer/',
            '/vendor/composer/ClassLoader.php',
            '/vendor/composer/LICENSE',
            '/vendor/composer/autoload_classmap.php',
            '/vendor/composer/autoload_namespaces.php',
            '/vendor/composer/autoload_psr4.php',
            '/vendor/composer/autoload_real.php',
            '/vendor/composer/autoload_static.php',
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        $this->assertSame($expectedFiles, $actualFiles);

        unset($phar);
        Phar::unlinkArchive('index.phar');
        // Executes the compilation again

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $this->assertSame(
            'Hello, world!',
            exec('php index.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_complete_mapping(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'check-requirements' => false,
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)


 // Loading the configuration file "/path/to/box.json.dist".

? Removing the existing PHAR "/path/to/tmp/test.phar"
Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
? Adding main file: /path/to/tmp/run.php
? Skip requirements checker
? Adding binary files
    > 1 file(s)
? Adding files
    > 4 file(s)
? Generating new stub
  - Using shebang line: #!/usr/bin/env php
  - Using banner:
    > Generated by Humbug Box.
    >
    > @link https://github.com/humbug/box
? Setting metadata
  - array (
  'rand' => $rand,
)
? Dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
? Setting file permissions to 0755
* Done.

 // PHAR: 13 files (100B)
 // You can inspect the generated PHAR with the "info" command.

 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual, 'Expected logs to be identical');

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        $this->assertSame(
            'Hello, world!',
            exec('cp test.phar test; php test'),
            'Expected PHAR can be renamed'
        );

        $phar = new Phar('test.phar');

        // Check PHAR content
        $actualStub = $this->normalizeDisplay($phar->getStub());
        $expectedStub = <<<'PHP'
#!/usr/bin/env php
<?php

/*
 * Generated by Humbug Box.
 *
 * @link https://github.com/humbug/box
 */

Phar::mapPhar('alias-test.phar');

require 'phar://alias-test.phar/run.php';

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
            '/vendor/',
            '/vendor/autoload.php',
            '/vendor/composer/',
            '/vendor/composer/ClassLoader.php',
            '/vendor/composer/LICENSE',
            '/vendor/composer/autoload_classmap.php',
            '/vendor/composer/autoload_namespaces.php',
            '/vendor/composer/autoload_psr4.php',
            '/vendor/composer/autoload_real.php',
            '/vendor/composer/autoload_static.php',
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        $this->assertSame($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_PHAR_with_complete_mapping_without_an_alias(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'check-requirements' => false,
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        $this->assertSame(
            'Hello, world!',
            exec('cp test.phar test; php test'),
            'Expected PHAR can be renamed'
        );

        $phar = new Phar('test.phar');

        // Check PHAR content
        $actualStub = preg_replace(
            '/box-auto-generated-alias-[\da-zA-Z]{13}\.phar/',
            'box-auto-generated-alias-__uniqid__.phar',
            $this->normalizeDisplay($phar->getStub())
        );

        $expectedStub = <<<'PHP'
#!/usr/bin/env php
<?php

/*
 * Generated by Humbug Box.
 *
 * @link https://github.com/humbug/box
 */

Phar::mapPhar('box-auto-generated-alias-__uniqid__.phar');

require 'phar://box-auto-generated-alias-__uniqid__.phar/run.php';

__HALT_COMPILER(); ?>

PHP;

        $this->assertSame($expectedStub, $actualStub);
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
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

? Removing the existing PHAR "/path/to/tmp/test.phar"
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
? Adding main file: /path/to/tmp/run.php
? Adding requirements checker
? Adding binary files
    > 1 file(s)
? Adding files
    > 4 file(s)
? Generating new stub
  - Using shebang line: $shebang
  - Using banner:
    > custom banner
? Setting metadata
  - array (
  'rand' => $rand,
)
? Dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 0755
* Done.

 // PHAR: 44 files (100B)
 // You can inspect the generated PHAR with the "info" command.

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
                    'banner' => [
                        'multiline',
                        'custom banner',
                    ],
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
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

? Removing the existing PHAR "/path/to/tmp/test.phar"
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
? Adding main file: /path/to/tmp/run.php
? Adding requirements checker
? Adding binary files
    > 1 file(s)
? Adding files
    > 4 file(s)
? Generating new stub
  - Using shebang line: #!__PHP_EXECUTABLE__
  - Using banner:
    > multiline
    > custom banner
? Setting metadata
  - array (
  'rand' => $rand,
)
? Dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 0755
* Done.

 // PHAR: 44 files (100B)
 // You can inspect the generated PHAR with the "info" command.

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

    public function test_it_can_build_a_PHAR_file_in_debug_mode(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => [
                        'multiline',
                        'custom banner',
                    ],
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

        $this->assertDirectoryNotExists('.box_dump');

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);    // Set input for the passphrase
        $commandTester->execute(
            [
                'command' => 'compile',
                '--debug' => null,
            ],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ]
        );

        $xdebugLog = extension_loaded('xdebug')
            ? '[debug] The xdebug extension is loaded (2.6.0)
[debug] No restart (BOX_ALLOW_XDEBUG=1)'
            : '[debug] The xdebug extension is not loaded'
        ;

        $expected = <<<OUTPUT
[debug] Checking BOX_ALLOW_XDEBUG
$xdebugLog

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)


 // Loading the configuration file "/path/to/box.json.dist".

? Removing the existing PHAR "/path/to/tmp/test.phar"
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
? Adding main file: /path/to/tmp/run.php
? Adding requirements checker
? Adding binary files
    > 1 file(s)
? Adding files
    > 4 file(s)
? Generating new stub
  - Using shebang line: #!__PHP_EXECUTABLE__
  - Using banner:
    > multiline
    > custom banner
? Setting metadata
  - array (
  'rand' => $rand,
)
? Dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 0755
* Done.

 // PHAR: 44 files (100B)
 // You can inspect the generated PHAR with the "info" command.

 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $expected = str_replace(
            '__PHP_EXECUTABLE__',
            (new PhpExecutableFinder())->find(),
            $expected
        );
        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertDirectoryExists('.box_dump');

        $expectedFiles = [
            '.box_dump/.box/.requirements.php',
            '.box_dump/.box/bin/check-requirements.php',
            '.box_dump/.box/check_requirements.php',
            '.box_dump/.box/composer.json',
            '.box_dump/.box/composer.lock',
            '.box_dump/.box/src/Checker.php',
            '.box_dump/.box/src/IO.php',
            '.box_dump/.box/src/IsExtensionFulfilled.php',
            '.box_dump/.box/src/IsFulfilled.php',
            '.box_dump/.box/src/IsPhpVersionFulfilled.php',
            '.box_dump/.box/src/Printer.php',
            '.box_dump/.box/src/Requirement.php',
            '.box_dump/.box/src/RequirementCollection.php',
            '.box_dump/.box/src/Terminal.php',
            '.box_dump/.box/vendor/autoload.php',
            '.box_dump/.box/vendor/composer/autoload_classmap.php',
            '.box_dump/.box/vendor/composer/autoload_namespaces.php',
            '.box_dump/.box/vendor/composer/autoload_psr4.php',
            '.box_dump/.box/vendor/composer/autoload_real.php',
            '.box_dump/.box/vendor/composer/autoload_static.php',
            '.box_dump/.box/vendor/composer/ClassLoader.php',
            '.box_dump/.box/vendor/composer/installed.json',
            '.box_dump/.box/vendor/composer/LICENSE',
            '.box_dump/.box/vendor/composer/semver/src/Comparator.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/AbstractConstraint.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/Constraint.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/ConstraintInterface.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/EmptyConstraint.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/MultiConstraint.php',
            '.box_dump/.box/vendor/composer/semver/src/Semver.php',
            '.box_dump/.box/vendor/composer/semver/src/VersionParser.php',
            '.box_dump/.box_configuration',
            '.box_dump/one/test.php',
            '.box_dump/run.php',
            '.box_dump/sub/test.php',
            '.box_dump/test.php',
            '.box_dump/two/test.png',
            '.box_dump/vendor/autoload.php',
            '.box_dump/vendor/composer/autoload_classmap.php',
            '.box_dump/vendor/composer/autoload_namespaces.php',
            '.box_dump/vendor/composer/autoload_psr4.php',
            '.box_dump/vendor/composer/autoload_real.php',
            '.box_dump/vendor/composer/autoload_static.php',
            '.box_dump/vendor/composer/ClassLoader.php',
            '.box_dump/vendor/composer/LICENSE',
        ];

        $actualFiles = $this->normalizePaths(
            iterator_to_array(
                Finder::create()->files()->in('.box_dump')->ignoreDotFiles(false)
            )
        );

        $this->assertSame($expectedFiles, $actualFiles);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        $expectedDumpedConfig = <<<EOF
//
// Processed content of the configuration file "/path/to/box.json" dumped for debugging purposes
// Time: 2018-05-24T20:59:15+00:00
//

KevinGH\Box\Configuration {#140
  -file: "/path/to/box.json"
  -fileMode: 493
  -alias: "test.phar"
  -basePath: "/path/to"
  -composerJson: array:2 [
    0 => "/path/to/composer.json"
    1 => array:1 [
      "autoload" => array:1 [
        "classmap" => array:1 [
          0 => "./"
        ]
      ]
    ]
  ]
  -composerLock: array:2 [
    0 => null
    1 => null
  ]
  -files: array:4 [
    0 => SplFileInfo {#140
      path: "/path/to"
      filename: "test.php"
      basename: "test.php"
      pathname: "/path/to/test.php"
      extension: "php"
      realPath: "/path/to/test.php"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 306
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
    1 => SplFileInfo {#140
      path: "/path/to"
      filename: "composer.json"
      basename: "composer.json"
      pathname: "/path/to/composer.json"
      extension: "json"
      realPath: "/path/to/composer.json"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 54
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
    2 => Symfony\Component\Finder\SplFileInfo {#140
      -relativePath: "deep/test/directory"
      -relativePathname: "deep/test/directory/test.php"
      path: "/path/to/a/deep/test/directory"
      filename: "test.php"
      basename: "test.php"
      pathname: "/path/to/a/deep/test/directory/test.php"
      extension: "php"
      realPath: "/path/to/a/deep/test/directory/test.php"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 0
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
    3 => Symfony\Component\Finder\SplFileInfo {#140
      -relativePath: ""
      -relativePathname: "test.php"
      path: "/path/to/one"
      filename: "test.php"
      basename: "test.php"
      pathname: "/path/to/one/test.php"
      extension: "php"
      realPath: "/path/to/one/test.php"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 0
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
  ]
  -binaryFiles: array:1 [
    0 => Symfony\Component\Finder\SplFileInfo {#140
      -relativePath: ""
      -relativePathname: "test.png"
      path: "/path/to/two"
      filename: "test.png"
      basename: "test.png"
      pathname: "/path/to/two/test.png"
      extension: "png"
      realPath: "/path/to/two/test.png"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 0
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
  ]
  -dumpAutoload: true
  -excludeComposerFiles: true
  -compactors: array:1 [
    0 => KevinGH\Box\Compactor\Php {#140
      -converter: Herrera\Annotations\Convert\ToString {#140
        -break: "\\n"
        -char: " "
        -level: null
        -space: false
        -size: 0
        #result: null
        #tokens: null
      }
      -tokenizer: Herrera\Annotations\Tokenizer {#140
        -aliases: []
        -ignored: []
        -lexer: Doctrine\Common\Annotations\DocLexer {#140
          #noCase: array:9 [
            "@" => 101
            "," => 104
            "(" => 109
            ")" => 103
            "{" => 108
            "}" => 102
            "=" => 105
            ":" => 112
            "\" => 107
          ]
          #withCase: array:3 [
            "true" => 110
            "false" => 106
            "null" => 111
          ]
          -input: null
          -tokens: []
          -position: 0
          -peek: 0
          +lookahead: null
          +token: null
        }
      }
      -extensions: array:1 [
        0 => "php"
      ]
    }
  ]
  -compressionAlgorithm: null
  -mainScriptPath: "/path/to/run.php"
  -mainScriptContents: """
    <?php\\n
    \\n
    declare(strict_types=1);\\n
    \\n
    /*\\n
     * This file is part of the box project.\\n
     *\\n
     * (c) Kevin Herrera <kevin@herrera.io>\\n
     *     Théo Fidry <theo.fidry@gmail.com>\\n
     *\\n
     * This source file is subject to the MIT license that is bundled\\n
     * with this source code in the file LICENSE.\\n
     */\\n
    \\n
    require 'test.php';\\n
    """
  -map: null
  -fileMapper: KevinGH\Box\MapFile {#140
    -map: array:1 [
      0 => array:1 [
        "a/deep/test/directory" => "sub"
      ]
    ]
  }
  -metadata: array:1 [
    "rand" => $rand
  ]
  -tmpOutputPath: "/path/to/test.phar"
  -outputPath: "/path/to/test.phar"
  -privateKeyPassphrase: null
  -privateKeyPath: "private.key"
  -isPrivateKeyPrompt: true
  -processedReplacements: []
  -shebang: "$shebang"
  -signingAlgorithm: 2
  -stubBannerContents: """
    multiline\\n
    custom banner
    """
  -stubBannerPath: null
  -stubPath: null
  -isInterceptFileFuncs: false
  -isStubGenerated: true
  -checkRequirements: true
}

EOF;

        $actualDumpedConfig = str_replace(
            $this->tmp,
            '/path/to',
            file_contents('.box_dump/.box_configuration')
        );

        $actualDumpedConfig = preg_replace(
            '/ \{#\d{3,}/',
            ' {#140',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/Time: \d{4,}-\d{2,}-\d{2,}T\d{2,}:\d{2,}:\d{2,}\+\d{2,}:\d{2,}/',
            'Time: 2018-05-24T20:59:15+00:00',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/([a-z]Time): \d{4,}-\d{2,}-\d{2,} \d{2,}:\d{2,}:\d{2,}/',
            '$1: 2018-05-24 20:59:15',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/inode: \d+/',
            'inode: 33452869',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/perms: \d+/',
            'perms: 0100644',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/owner: \d+/',
            'owner: 501',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/group: \d+/',
            'group: 20',
            $actualDumpedConfig
        );

        $this->assertSame($expectedDumpedConfig, $actualDumpedConfig);
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

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'compile'],
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
                    'stub' => false,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);    // Set input for the passphrase
        $commandTester->execute(
            ['command' => 'compile'],
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
    require 'phar://' . __FILE__ . '/run.php';
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
                    'stub' => 'custom_stub',
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);    // Set input for the passphrase
        $commandTester->execute(
            ['command' => 'compile'],
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

    public function test_it_can_build_a_PHAR_file_using_the_default_stub(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'directories' => ['a'],
                    'files' => ['test.php'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'output' => 'test.phar',
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_cannot_build_a_PHAR_using_unreadable_files(): void
    {
        touch('index.php');
        touch('unreadable-file.php');
        chmod('unreadable-file.php', 0000);

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'files' => ['unreadable-file.php'],
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        try {
            $commandTester->execute(
                ['command' => 'compile'],
                [
                    'interactive' => false,
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertRegExp(
                '/^Path ".+?" was expected to be readable\.$/',
                $exception->getMessage()
            );
        }
    }

    public function test_it_can_build_a_PHAR_overwriting_an_existing_one_in_verbose_mode(): void
    {
        mirror(self::FIXTURES_DIR.'/dir002', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

? Removing the existing PHAR "/path/to/tmp/test.phar"
* Building the PHAR "/path/to/tmp/test.phar"
? Setting replacement values
  + @name@: world
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Skip requirements checker
? Adding binary files
    > No file found
? Adding files
    > No file found
? Generating new stub
  - Using shebang line: #!/usr/bin/env php
  - Using banner:
    > Generated by Humbug Box.
    >
    > @link https://github.com/humbug/box
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
* Done.

 // PHAR: 1 file (100B)
 // You can inspect the generated PHAR with the "info" command.

 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_dump_the_autoloader(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        rename('run.php', 'index.php');

        $commandTester = $this->getCommandTester();

        $this->assertFileNotExists($this->tmp.'/vendor/autoload.php');

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $output = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame(
            1,
            preg_match('/\? Dumping the Composer autoloader/', $output),
            'Expected the autoloader to be dumped'
        );

        $composerFiles = [
            'vendor/autoload.php',
            'vendor/composer/',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/LICENSE',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
        ];

        foreach ($composerFiles as $composerFile) {
            $this->assertFileExists('phar://index.phar/'.$composerFile);
        }
    }

    public function test_it_can_build_a_PHAR_without_dumping_the_autoloader(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        rename('run.php', 'index.php');

        $commandTester = $this->getCommandTester();

        $this->assertFileNotExists($this->tmp.'/vendor/autoload.php');

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'dump-autoload' => false,
                ]
            )
        );

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $output = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame(
            1,
            preg_match('/\? Skipping dumping the Composer autoloader/', $output),
            'Did not expect the autoloader to be dumped'
        );

        $composerFiles = [
            'vendor/autoload.php',
            'vendor/composer/',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/LICENSE',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
        ];

        foreach ($composerFiles as $composerFile) {
            $this->assertFileNotExists('phar://index.phar/'.$composerFile);
        }
    }

    public function test_it_can_dump_the_autoloader_and_exclude_the_composer_files(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        rename('run.php', 'index.php');

        $commandTester = $this->getCommandTester();

        $this->assertFileNotExists($this->tmp.'/vendor/autoload.php');

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $output = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame(
            1,
            preg_match('/\? Removing the Composer dump artefacts/', $output),
            'Expected the composer files to be removed'
        );

        $composerFiles = [
            'vendor/autoload.php',
            'vendor/composer/',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/LICENSE',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
        ];

        foreach ($composerFiles as $composerFile) {
            $this->assertFileExists('phar://index.phar/'.$composerFile);
        }

        $removedComposerFiles = [
            'composer.json',
            'composer.lock',
            'vendor/composer/installed.json',
        ];

        foreach ($removedComposerFiles as $composerFile) {
            $this->assertFileNotExists('phar://index.phar/'.$composerFile);
        }
    }

    public function test_it_can_dump_the_autoloader_and_keep_the_composer_files(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        rename('run.php', 'index.php');

        file_put_contents(
            'box.json',
            json_encode(['exclude-composer-files' => false])
        );

        $commandTester = $this->getCommandTester();

        $this->assertFileNotExists($this->tmp.'/vendor/autoload.php');

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $output = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame(
            1,
            preg_match('/\? Keep the Composer dump artefacts/', $output),
            'Expected the composer files to be kept'
        );

        $composerFiles = [
            'vendor/autoload.php',
            'vendor/composer/',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/LICENSE',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
        ];

        foreach ($composerFiles as $composerFile) {
            $this->assertFileExists('phar://index.phar/'.$composerFile);
        }

        $removedComposerFiles = [
            'composer.json',
            // The following two files do not exists since there is no dependency, check BoxTest for a more complete
            // test regarding this feature
            //'composer.lock',
            //'vendor/composer/installed.json',
        ];

        foreach ($removedComposerFiles as $composerFile) {
            $this->assertFileExists('phar://index.phar/'.$composerFile);
        }
    }

    public function test_it_can_build_a_PHAR_with_a_custom_banner(): void
    {
        mirror(self::FIXTURES_DIR.'/dir003', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            [
                'command' => 'compile',
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


 // Loading the configuration file "/path/to/box.json.dist".

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Skip requirements checker
? Adding binary files
    > No file found
? Adding files
    > No file found
? Generating new stub
  - Using shebang line: #!/usr/bin/env php
  - Using custom banner from file: /path/to/tmp/banner
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
* Done.

 // PHAR: 1 file (100B)
 // You can inspect the generated PHAR with the "info" command.

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
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Skip requirements checker
? Adding binary files
    > No file found
? Adding files
    > No file found
? Using stub file: /path/to/tmp/stub.php
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
* Done.

 // PHAR: 1 file (100B)
 // You can inspect the generated PHAR with the "info" command.

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
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding main file: /path/to/tmp/index.php
? Skip requirements checker
? Adding binary files
    > No file found
? Adding files
    > 1 file(s)
? Generating new stub
  - Using shebang line: #!/usr/bin/env php
  - Using banner:
    > Generated by Humbug Box.
    >
    > @link https://github.com/humbug/box
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
* Done.

 // PHAR: 2 files (100B)
 // You can inspect the generated PHAR with the "info" command.

 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_without_a_main_script(): void
    {
        mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

        dump_file(
            'box.json',
            <<<'JSON'
{
    "files-bin": ["test.php"],
    "stub": "stub.php",
    "main": false
}
JSON
        );

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? No main script path configured
? Skip requirements checker
? Adding binary files
    > 1 file(s)
? Adding files
    > No file found
? Using stub file: /path/to/tmp/stub.php
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
* Done.

 // PHAR: 1 file (100B)
 // You can inspect the generated PHAR with the "info" command.

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

    public function test_it_can_build_an_empty_PHAR(): void
    {
        mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

        dump_file(
            'box.json',
            <<<'JSON'
{
    "stub": "stub.php",
    "main": false
}
JSON
        );

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? No main script path configured
? Skip requirements checker
? Adding binary files
    > No file found
? Adding files
    > No file found
? Using stub file: /path/to/tmp/stub.php
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
* Done.

 // PHAR: 1 file (100B)
 // You can inspect the generated PHAR with the "info" command.

 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        $expectedFiles = [
            '/.box_empty',
        ];

        $actualFiles = $this->retrievePharFiles(new Phar('test.phar'));

        $this->assertSame($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_PHAR_with_compressed_code(): void
    {
        mirror(self::FIXTURES_DIR.'/dir006', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Skip requirements checker
? Adding binary files
    > No file found
? Adding files
    > No file found
? Generating new stub
  - Using shebang line: #!/usr/bin/env php
  - Using banner:
    > Generated by Humbug Box.
    >
    > @link https://github.com/humbug/box
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? Compressing with the algorithm "GZ"
    > Warning: the extension "zlib" will now be required to execute the PHAR
* Done.

 // PHAR: 1 file (100B)
 // You can inspect the generated PHAR with the "info" command.

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
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

* Building the PHAR "/path/to/tmp/foo/bar/test.phar"
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Skip requirements checker
? Adding binary files
    > No file found
? Adding files
    > No file found
? Generating new stub
  - Using shebang line: #!/usr/bin/env php
  - Using banner:
    > Generated by Humbug Box.
    >
    > @link https://github.com/humbug/box
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
* Done.

 // PHAR: 1 file (100B)
 // You can inspect the generated PHAR with the "info" command.

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
    public function test_it_configures_the_PHAR_alias(bool $stub): void
    {
        mirror(self::FIXTURES_DIR.'/dir008', $this->tmp);

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => $alias = 'alias-test.phar',
                    'main' => 'index.php',
                    'stub' => $stub,
                    'blacklist' => ['box.json'],
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'compile'],
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
            exec('php index.phar'),
            'Expected PHAR to be executable'
        );

        $phar = new Phar('index.phar');

        // Check the stub content
        $actualStub = DisplayNormalizer::removeTrailingSpaces($phar->getStub());
        $defaultStub = DisplayNormalizer::removeTrailingSpaces(file_get_contents(self::FIXTURES_DIR.'/../default_stub.php'));

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

        $expectedFiles = [
            '/index.php',
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        $this->assertSame($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_PHAR_file_without_a_shebang_line(): void
    {
        mirror(self::FIXTURES_DIR.'/dir006', $this->tmp);

        $boxRawConfig = json_decode(file_get_contents('box.json'), true, 512, JSON_PRETTY_PRINT);
        $boxRawConfig['shebang'] = null;
        file_put_contents('box.json', json_encode($boxRawConfig), JSON_PRETTY_PRINT);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Skip requirements checker
? Adding binary files
    > No file found
? Adding files
    > No file found
? Generating new stub
  - No shebang line
  - Using banner:
    > Generated by Humbug Box.
    >
    > @link https://github.com/humbug/box
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? Compressing with the algorithm "GZ"
    > Warning: the extension "zlib" will now be required to execute the PHAR
* Done.

 // PHAR: 1 file (100B)
 // You can inspect the generated PHAR with the "info" command.

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

    public function test_it_can_build_a_PHAR_with_an_output_which_does_not_have_a_PHAR_extension(): void
    {
        mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

        file_put_contents(
            'box.json',
            json_encode(
                array_merge(
                    json_decode(
                        file_get_contents('box.json'),
                        true
                    ),
                    ['output' => 'test']
                )
            )
        );

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'compile'],
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


 // Loading the configuration file "/path/to/box.json.dist".

* Building the PHAR "/path/to/tmp/test"
? No compactor to register
? Adding main file: /path/to/tmp/test.php
? Skip requirements checker
? Adding binary files
    > No file found
? Adding files
    > No file found
? Using stub file: /path/to/tmp/stub.php
? Skipping dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
* Done.

 // PHAR: 1 file (100B)
 // You can inspect the generated PHAR with the "info" command.

 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php test'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_ignoring_the_configuration(): void
    {
        mirror(self::FIXTURES_DIR.'/dir009', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            [
                'command' => 'compile',
                '--no-config' => null,
            ],
            ['interactive' => true]
        );

        $this->assertSame(
            'Index',
            exec('php index.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_ignores_the_config_given_when_the_no_config_setting_is_set(): void
    {
        mirror(self::FIXTURES_DIR.'/dir009', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            [
                'command' => 'compile',
                '--config' => 'box.json',
                '--no-config' => null,
            ],
            ['interactive' => true]
        );

        $this->assertSame(
            'Index',
            exec('php index.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_a_PHPScoper_config(): void
    {
        mirror(self::FIXTURES_DIR.'/dir010', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $this->assertSame(
            'Index',
            exec('php index.phar'),
            'Expected PHAR to be executable'
        );

        $this->assertSame(
            1,
            preg_match(
                '/namespace (?<namespace>.*);/',
                $indexContents = file_get_contents('phar://index.phar/index.php'),
                $matches
            ),
            sprintf(
                'Expected the content of the PHAR index.php file to match the given regex. The following '
                .'contents does not: "%s"',
                $indexContents
            )
        );

        $phpScoperNamespace = $matches['namespace'];

        $this->assertStringStartsWith('_HumbugBox', $phpScoperNamespace);
    }

    public function test_it_can_build_a_PHAR_with_a_PHPScoper_config_with_a_specific_prefix(): void
    {
        mirror(self::FIXTURES_DIR.'/dir010', $this->tmp);

        rename('scoper-fixed-prefix.inc.php', 'scoper.inc.php', true);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'compile'],
            ['interactive' => true]
        );

        $this->assertSame(
            'Index',
            exec('php index.phar'),
            'Expected PHAR to be executable'
        );

        $this->assertSame(
            1,
            preg_match(
                '/namespace (?<namespace>.*);/',
                $indexContents = file_get_contents('phar://index.phar/index.php'),
                $matches
            ),
            sprintf(
                'Expected the content of the PHAR index.php file to match the given regex. The following '
                .'contents does not: "%s"',
                $indexContents
            )
        );

        $phpScoperNamespace = $matches['namespace'];

        $this->assertSame('Acme', $phpScoperNamespace);
    }

    public function provideAliasConfig(): Generator
    {
        yield [true];
        yield [false];
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Compile();
    }

    private function normalizeDisplay(string $display)
    {
        $display = str_replace($this->tmp, '/path/to/tmp', $display);

        $display = preg_replace(
            '/Loading the configuration file[\s\n]+.*[\s\n\/]+.*box\.json[comment\<\>\n\s\/]*"\./',
            'Loading the configuration file "/path/to/box.json.dist".',
            $display
        );

        $display = preg_replace(
            '/You can inspect the generated PHAR( | *\n *\/\/ *)with( | *\n *\/\/ *)the( | *\n *\/\/ *)"info"( | *\n *\/\/ *)command/',
            'You can inspect the generated PHAR with the "info" command',
            $display
        );

        $display = preg_replace(
            '/\/\/ PHAR: (\d+ files?) \(\d+\.\d{2}K?B\)/',
            '// PHAR: $1 (100B)',
            $display
        );

        $display = preg_replace(
            '/\/\/ Memory usage: \d+\.\d{2}MB \(peak: \d+\.\d{2}MB\), time: \d+\.\d{2}s/',
            '// Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s',
            $display
        );

        return DisplayNormalizer::removeTrailingSpaces($display);
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
}
