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
use function file_get_contents;
use Generator;
use InvalidArgumentException;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Console\DisplayNormalizer;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use PharFileInfo;
use function sprintf;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Traversable;
use function file_put_contents;
use function ini_get;
use function ini_set;
use function KevinGH\Box\FileSystem\mirror;
use function KevinGH\Box\FileSystem\rename;
use function preg_match;
use function preg_replace;
use function putenv;
use function sort;

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
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 0755
* Done.

 // PHAR: 46 files (100B)
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
            '/composer.json',
            '/composer.lock',
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
? No compression
* Done.

 // PHAR: 49 files (100B)
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
            '/composer.json',
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
? No compression
? Setting file permissions to 0755
* Done.

 // PHAR: 14 files (100B)
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
            '/composer.json',
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
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 0755
* Done.

 // PHAR: 45 files (100B)
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
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 0755
* Done.

 // PHAR: 45 files (100B)
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

    public function test_it_can_build_a_PHAR_with_a_replacement_placeholder(): void
    {
        mirror(self::FIXTURES_DIR.'/dir001', $this->tmp);

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

* Building the PHAR "/path/to/tmp/test.phar"
? Setting replacement values
  + @name@: world
? No compactor to register
? Adding main file: /path/to/tmp/test.php
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
? Adding binary files
    > No file found
? Adding files
    > No file found
? Generating new stub
  - Using shebang line: #!/usr/bin/env php
  - Using custom banner from file: /path/to/tmp/banner
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
? Adding binary files
    > No file found
? Adding files
    > No file found
? Using stub file: /path/to/tmp/stub.php
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
? No compression
* Done.

 // PHAR: 2 files (100B)
 // You can inspect the generated PHAR with the "info" command.

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
? Adding binary files
    > No file found
? Adding files
    > No file found
? Using stub file: /path/to/tmp/stub.php
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

    /**
     * @dataProvider provideMemoryLimits
     */
    public function test_it_bumps_the_memory_limit_when_necessary(string $initialMemoryLimit, ?string $boxMemoryLimit, string $expectedMemoryLimit): void
    {
        $originalMemoryLimit = ini_get('memory_limit');

        try {
            mirror(self::FIXTURES_DIR.'/dir010', $this->tmp);

            ini_set('memory_limit', $initialMemoryLimit);

            if (null !== $boxMemoryLimit) {
                putenv('BOX_MEMORY_LIMIT='.$boxMemoryLimit);
            }

            $from = ini_get('memory_limit');

            $commandTester = $this->getCommandTester();

            $commandTester->execute(
                ['command' => 'compile'],
                ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
            );

            $this->assertSame(
                'Index',
                exec('php index.phar'),
                'Expected PHAR to be executable'
            );

            $display = $this->normalizeDisplay($commandTester->getDisplay(true));

            if (1 === preg_match(
                '/\[debug\] Bumped the memory limit from "(?<from>.+?)" to (?:BOX_MEMORY_LIMIT=)?"(?<to>.+?)"\./',
                $display,
                $matches
            )
            ) {
                [$from, $to] = [$matches['from'], $matches['to']];
            } else {
                $to = ini_get('memory_limit');
            }

            $this->assertSame($initialMemoryLimit, $from, 'Expected the initial memory limit to have been correctly set.');
            $this->assertSame($expectedMemoryLimit, $to, 'Expected the memory limit to have been correctly set.');
        } finally {
            ini_set('memory_limit', $originalMemoryLimit);
            putenv('BOX_MEMORY_LIMIT');
        }
    }

    public function provideMemoryLimits()
    {
        yield ['126M', null, '512M'];
        yield ['126M', '252M', '252M'];
        yield ['126M', '638M', '638M'];

        yield ['512M', null, '512M'];
        yield ['512M', '252M', '252M'];
        yield ['512M', '638M', '638M'];

        yield ['1024M', null, '1024M'];
        yield ['1024M', '252M', '252M'];
        yield ['1024M', '1150M', '1150M'];
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
