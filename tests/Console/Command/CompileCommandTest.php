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
use Fidry\Console\Bridge\Command\SymfonyCommand;
use Fidry\Console\Command\Command;
use Fidry\Console\DisplayNormalizer;
use Fidry\Console\ExitCode;
use Fidry\Console\Test\CommandTester;
use Fidry\Console\Test\OutputAssertions;
use Fidry\FileSystem\FS;
use InvalidArgumentException;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Console\Application;
use KevinGH\Box\Console\DisplayNormalizer as BoxDisplayNormalizer;
use KevinGH\Box\Console\MessageRenderer;
use KevinGH\Box\RequirementChecker\AppRequirementsFactory;
use KevinGH\Box\RequirementChecker\RequirementsDumper;
use KevinGH\Box\Test\FileSystemTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use KevinGH\Box\VarDumperNormalizer;
use Phar;
use PharFileInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Traversable;
use function array_merge;
use function array_unique;
use function chdir;
use function exec;
use function extension_loaded;
use function file_get_contents;
use function get_loaded_extensions;
use function implode;
use function iterator_to_array;
use function json_decode;
use function json_encode;
use function KevinGH\Box\format_size;
use function KevinGH\Box\get_box_version;
use function KevinGH\Box\memory_to_bytes;
use function mt_getrandmax;
use function phpversion;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function random_int;
use function Safe\realpath;
use function sort;
use function sprintf;
use function str_replace;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const PHP_OS;
use const PHP_VERSION;

/**
 * @internal
 */
#[CoversClass(CompileCommand::class)]
#[CoversClass(MessageRenderer::class)]
#[RunTestsInSeparateProcesses]
class CompileCommandTest extends FileSystemTestCase
{
    use RequiresPharReadonlyOff;

    private const NUMBER_OF_FILES = 48;

    private const BOX_FILES = [
        '/.box/',
        '/.box/.requirements.php',
        '/.box/bin/',
        '/.box/bin/check-requirements.php',
        '/.box/src/',
        '/.box/src/Checker.php',
        '/.box/src/IO.php',
        '/.box/src/IsExtensionConflictFulfilled.php',
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
        '/.box/vendor/composer/InstalledVersions.php',
        '/.box/vendor/composer/installed.php',
        '/.box/vendor/composer/LICENSE',
        '/.box/vendor/composer/autoload_classmap.php',
        '/.box/vendor/composer/autoload_namespaces.php',
        '/.box/vendor/composer/autoload_psr4.php',
        '/.box/vendor/composer/autoload_real.php',
        '/.box/vendor/composer/autoload_static.php',
        '/.box/vendor/composer/semver/',
        '/.box/vendor/composer/semver/LICENSE',
        '/.box/vendor/composer/semver/src/',
        '/.box/vendor/composer/semver/src/Comparator.php',
        '/.box/vendor/composer/semver/src/CompilingMatcher.php',
        '/.box/vendor/composer/semver/src/Constraint/',
        '/.box/vendor/composer/semver/src/Constraint/Bound.php',
        '/.box/vendor/composer/semver/src/Constraint/Constraint.php',
        '/.box/vendor/composer/semver/src/Constraint/ConstraintInterface.php',
        '/.box/vendor/composer/semver/src/Constraint/MatchAllConstraint.php',
        '/.box/vendor/composer/semver/src/Constraint/MatchNoneConstraint.php',
        '/.box/vendor/composer/semver/src/Constraint/MultiConstraint.php',
        '/.box/vendor/composer/semver/src/Interval.php',
        '/.box/vendor/composer/semver/src/Intervals.php',
        '/.box/vendor/composer/semver/src/Semver.php',
        '/.box/vendor/composer/semver/src/VersionParser.php',
    ];

    private const COMPOSER_FILES = [
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

    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/build';
    private const DEFAULT_STUB_PATH = __DIR__.'/../../../dist/default_stub.php';

    protected CommandTester $commandTester;
    protected Command $command;

    protected function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        parent::setUp();

        $this->command = $this->getCommand();

        $command = new SymfonyCommand($this->command);

        $application = new SymfonyApplication();
        $application->add($command);
        $application->add(new SymfonyCommand(new GenerateDockerFileCommand()));

        $this->commandTester = new CommandTester(
            $application->get(
                $command->getName(),
            ),
        );

        FS::remove(self::FIXTURES_DIR.'/dir010/index.phar');
    }

    protected function tearDown(): void
    {
        unset($this->command, $this->commandTester);

        parent::tearDown();
    }

    protected function getCommand(): Command
    {
        return new CompileCommand(
            (new Application())->getHeader(),
            new RequirementsDumper(
                new AppRequirementsFactory(),
            ),
        );
    }

    public function test_it_can_build_a_phar_file(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        FS::dumpFile('composer.json', '{}');
        FS::dumpFile('composer.lock', '{}');
        FS::dumpFile('vendor/composer/installed.json', '{}');

        $shebang = self::getExpectedShebang();

        $numberOfFiles = self::NUMBER_OF_FILES;

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'chmod' => '0700',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php', 'vendor/composer/installed.json'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'algorithm' => 'OPENSSL',
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->commandTester->setInputs(['test']);    // Set input for the passphrase
        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Removing the existing PHAR "/path/to/tmp/test.phar"
            ? Checking Composer compatibility
                > Supported version detected
            ? Registering compactors
              + KevinGH\\Box\\Compactor\\Php
            ? Mapping paths
              - a/deep/test/directory > sub
            ? Adding main file: /path/to/tmp/run.php
            ? Adding requirements checker
            ? Adding binary files
                > 1 file(s)
            ? Auto-discover files? No
            ? Exclude dev files? Yes
            ? Adding files
                > 6 file(s)
            ? Generating new stub
              - Using shebang line: {$shebang}
              - Using banner:
                > custom banner
            ? Setting metadata
              - array (
              'rand' => {$rand},
            )
            ? Dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Signing using a private key

             Private key passphrase:
             >

            ? Setting file permissions to 0700
            * Done.

            No recommendation found.
            ⚠️  <warning>2 warnings found:</warning>
                - Using the "metadata" setting is deprecated and will be removed in 5.0.0.
                - Using an OpenSSL signature is deprecated and will be removed in 5.0.0. Please check https://github.com/box-project/box/blob/main/doc/phar-signing.md for alternatives.

             // PHAR: {$numberOfFiles} files (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );

        $phar = new Phar('test.phar');

        self::assertSame('OpenSSL', $phar->getSignature()['hash_type']);

        // Check PHAR content
        $actualStub = self::normalizeStub($phar->getStub());
        $expectedStub = <<<PHP
            {$shebang}
            <?php

            /*
             * custom banner
             */

            Phar::mapPhar('alias-test.phar');

            require 'phar://alias-test.phar/.box/bin/check-requirements.php';

            \$_SERVER['SCRIPT_FILENAME'] = 'phar://alias-test.phar/run.php';
            require 'phar://alias-test.phar/run.php';

            __HALT_COMPILER(); ?>

            PHP;

        self::assertSame($expectedStub, $actualStub);

        self::assertSame(
            ['rand' => $rand],
            $phar->getMetadata(),
            'Expected PHAR metadata to be set',
        );

        $expectedFiles = [
            ...self::BOX_FILES,
            ...self::COMPOSER_FILES,
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

        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_phar_from_a_different_directory(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = self::getExpectedShebang();

        FS::dumpFile(
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
                    'algorithm' => 'OPENSSL',
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        chdir($this->cwd);

        $this->commandTester->setInputs(['test']);    // Set input for the passphrase
        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
                '--working-dir' => $this->tmp,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_can_build_a_phar_without_any_configuration(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        FS::rename('run.php', 'index.php');

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        $version = get_box_version();
        $expectedNumberOfFiles = self::NUMBER_OF_FILES + 5;

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading without a configuration file.

            🔨  Building the PHAR "/path/to/tmp/index.phar"

            ? Checking Composer compatibility
                > Supported version detected
            ? No compactor to register
            ? Adding main file: /path/to/tmp/index.php
            ? Adding requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? Yes
            ? Adding files
                > 10 file(s)
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: {$expectedNumberOfFiles} files (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello, world!',
            exec('php index.phar'),
            'Expected PHAR to be executable',
        );

        $phar = new Phar('index.phar');

        self::assertSame('SHA-512', $phar->getSignature()['hash_type']);

        // Check PHAR content
        $actualStub = self::normalizeStub($phar->getStub());

        $expectedStub = <<<PHP
            #!/usr/bin/env php
            <?php

            /*
             * Generated by Humbug Box {$version}.
             *
             * @link https://github.com/humbug/box
             */

            Phar::mapPhar('box-auto-generated-alias-__uniqid__.phar');

            require 'phar://box-auto-generated-alias-__uniqid__.phar/.box/bin/check-requirements.php';

            \$_SERVER['SCRIPT_FILENAME'] = 'phar://box-auto-generated-alias-__uniqid__.phar/index.php';
            require 'phar://box-auto-generated-alias-__uniqid__.phar/index.php';

            __HALT_COMPILER(); ?>

            PHP;

        self::assertSame($expectedStub, $actualStub);

        self::assertNull(
            $phar->getMetadata(),
            'Expected PHAR metadata to be set',
        );

        $expectedFiles = [
            ...self::BOX_FILES,
            ...self::COMPOSER_FILES,
            '/a/',
            '/a/deep/',
            '/a/deep/test/',
            '/a/deep/test/directory/',
            '/a/deep/test/directory/test.php',
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
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);

        unset($phar);
        Phar::unlinkArchive('index.phar');
        // Executes the compilation again

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Hello, world!',
            exec('php index.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_can_build_a_phar_with_complete_mapping(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'check-requirements' => false,
                    'chmod' => '0754',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'output' => 'test.phar',
                ],
            ),
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        $version = get_box_version();

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Removing the existing PHAR "/path/to/tmp/test.phar"
            ? Checking Composer compatibility
                > Supported version detected
            ? Registering compactors
              + KevinGH\\Box\\Compactor\\Php
            ? Mapping paths
              - a/deep/test/directory > sub
            ? Adding main file: /path/to/tmp/run.php
            ? Skip requirements checker
            ? Adding binary files
                > 1 file(s)
            ? Auto-discover files? No
            ? Exclude dev files? Yes
            ? Adding files
                > 4 file(s)
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0754
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 13 files (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );

        self::assertSame(
            'Hello, world!',
            exec('cp test.phar test; php test'),
            'Expected PHAR can be renamed',
        );

        $phar = new Phar('test.phar');

        // Check PHAR content
        $actualStub = self::normalizeStub($phar->getStub());
        $expectedStub = <<<PHP
            #!/usr/bin/env php
            <?php

            /*
             * Generated by Humbug Box {$version}.
             *
             * @link https://github.com/humbug/box
             */

            Phar::mapPhar('alias-test.phar');

            \$_SERVER['SCRIPT_FILENAME'] = 'phar://alias-test.phar/run.php';
            require 'phar://alias-test.phar/run.php';

            __HALT_COMPILER(); ?>

            PHP;

        self::assertSame($expectedStub, $actualStub);

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

        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_phar_with_complete_mapping_without_an_alias(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'check-requirements' => false,
                    'chmod' => '0754',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                ],
            ),
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );

        self::assertSame(
            'Hello, world!',
            exec('cp test.phar test; php test'),
            'Expected PHAR can be renamed',
        );

        $phar = new Phar('test.phar');

        // Check PHAR content
        $actualStub = self::normalizeStub($phar->getStub());

        $version = get_box_version();

        $expectedStub = <<<PHP
            #!/usr/bin/env php
            <?php

            /*
             * Generated by Humbug Box {$version}.
             *
             * @link https://github.com/humbug/box
             */

            Phar::mapPhar('box-auto-generated-alias-__uniqid__.phar');

            \$_SERVER['SCRIPT_FILENAME'] = 'phar://box-auto-generated-alias-__uniqid__.phar/run.php';
            require 'phar://box-auto-generated-alias-__uniqid__.phar/run.php';

            __HALT_COMPILER(); ?>

            PHP;

        self::assertSame($expectedStub, $actualStub);
    }

    public function test_it_can_build_a_phar_file_in_verbose_mode(): void
    {
        if (extension_loaded('xdebug')) {
            self::markTestSkipped('Skipping this test since xdebug changes the Composer output.');
        }

        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = self::getExpectedShebang();

        $expectedNumberOfClasses = 1;
        $expectedNumberOfFiles = self::NUMBER_OF_FILES;

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => 'custom banner',
                    'chmod' => '0754',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'algorithm' => 'OPENSSL',
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->commandTester->setInputs(['test']);    // Set input for the passphrase
        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Removing the existing PHAR "/path/to/tmp/test.phar"
            ? Checking Composer compatibility
                > '/usr/local/bin/composer' '--version' '--no-ansi'
                > Version detected: 2.2.22 (Box requires ^2.2.0)
                > Supported version detected
            ? Registering compactors
              + KevinGH\\Box\\Compactor\\Php
            ? Mapping paths
              - a/deep/test/directory > sub
            ? Adding main file: /path/to/tmp/run.php
            ? Adding requirements checker
            ? Adding binary files
                > 1 file(s)
            ? Auto-discover files? No
            ? Exclude dev files? Yes
            ? Adding files
                > 4 file(s)
            ? Generating new stub
              - Using shebang line: {$shebang}
              - Using banner:
                > custom banner
            ? Setting metadata
              - array (
              'rand' => {$rand},
            )
            ? Dumping the Composer autoloader
                > '/usr/local/bin/composer' 'dump-autoload' '--classmap-authoritative' '--no-dev'
            Generating optimized autoload files (authoritative)
            Generated optimized autoload files (authoritative) containing {$expectedNumberOfClasses} classes

            ? Removing the Composer dump artefacts
            ? No compression
            ? Signing using a private key

             Private key passphrase:
             >

            ? Setting file permissions to 0754
            * Done.

            No recommendation found.
            ⚠️  <warning>2 warnings found:</warning>
                - Using the "metadata" setting is deprecated and will be removed in 5.0.0.
                - Using an OpenSSL signature is deprecated and will be removed in 5.0.0. Please check https://github.com/box-project/box/blob/main/doc/phar-signing.md for alternatives.

             // PHAR: {$expectedNumberOfFiles} files (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput(
            $expected,
            ExitCode::SUCCESS,
            self::createComposerPathNormalizer(),
        );
    }

    public function test_it_can_build_a_phar_file_in_very_verbose_mode(): void
    {
        if (extension_loaded('xdebug')) {
            self::markTestSkipped('Skipping this test since xdebug changes the Composer output');
        }

        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = self::getExpectedShebang();

        $expectedNumberOfClasses = 1;
        $expectedNumberOfFiles = self::NUMBER_OF_FILES;

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => [
                        'multiline',
                        'custom banner',
                    ],
                    'chmod' => '0754',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'algorithm' => 'OPENSSL',
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->commandTester->setInputs(['test']);    // Set input for the passphrase
        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE,
            ],
        );

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Removing the existing PHAR "/path/to/tmp/test.phar"
            ? Checking Composer compatibility
                > '/usr/local/bin/composer' '--version' '--no-ansi'
                > Version detected: 2.2.22 (Box requires ^2.2.0)
                > Supported version detected
            ? Registering compactors
              + KevinGH\\Box\\Compactor\\Php
            ? Mapping paths
              - a/deep/test/directory > sub
            ? Adding main file: /path/to/tmp/run.php
            ? Adding requirements checker
            ? Adding binary files
                > 1 file(s)
            ? Auto-discover files? No
            ? Exclude dev files? Yes
            ? Adding files
                > 4 file(s)
            ? Generating new stub
              - Using shebang line: #!__PHP_EXECUTABLE__
              - Using banner:
                > multiline
                > custom banner
            ? Setting metadata
              - array (
              'rand' => {$rand},
            )
            ? Dumping the Composer autoloader
                > '/usr/local/bin/composer' 'dump-autoload' '--classmap-authoritative' '--no-dev' '-v'
            Generating optimized autoload files (authoritative)
            Generated optimized autoload files (authoritative) containing {$expectedNumberOfClasses} classes

            ? Removing the Composer dump artefacts
            ? No compression
            ? Signing using a private key

             Private key passphrase:
             >

            ? Setting file permissions to 0754
            * Done.

            No recommendation found.
            ⚠️  <warning>2 warnings found:</warning>
                - Using the "metadata" setting is deprecated and will be removed in 5.0.0.
                - Using an OpenSSL signature is deprecated and will be removed in 5.0.0. Please check https://github.com/box-project/box/blob/main/doc/phar-signing.md for alternatives.

             // PHAR: {$expectedNumberOfFiles} files (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $expected = str_replace(
            '__PHP_EXECUTABLE__',
            (new PhpExecutableFinder())->find(),
            $expected,
        );

        $this->assertSameOutput(
            $expected,
            ExitCode::SUCCESS,
            self::createComposerPathNormalizer(),
        );
    }

    public function test_it_can_build_a_phar_file_in_debug_mode(): void
    {
        FS::dumpFile(
            'index.php',
            $indexContents = <<<'PHP'
                <?php

                declare(strict_types=1);

                echo 'Yo';

                PHP,
        );
        FS::dumpFile(
            'box.json',
            <<<'JSON'
                {
                    "alias": "index.phar",
                    "banner": ""
                }
                JSON,
        );

        self::assertDirectoryDoesNotExist('.box_dump');

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
                '--debug' => null,
            ],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        if (extension_loaded('xdebug')) {
            $xdebugVersion = sprintf(
                '(%s)',
                phpversion('xdebug'),
            );

            $xdebugLog = "[debug] The xdebug extension is loaded {$xdebugVersion} xdebug.mode=debug
[debug] No restart (BOX_ALLOW_XDEBUG=1)";
        } else {
            $xdebugLog = '[debug] The xdebug extension is not loaded';
        }

        $memoryLog = sprintf(
            '[debug] Current memory limit: "%s"',
            format_size(memory_to_bytes(trim(ini_get('memory_limit'))), 0),
        );

        $expected = <<<OUTPUT
            {$memoryLog}
            [debug] Checking BOX_ALLOW_XDEBUG
            {$xdebugLog}
            [debug] Disabled parallel processing

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/index.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/index.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                >
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertDirectoryExists('.box_dump');

        $expectedFiles = [
            '.box_dump/.box_configuration',
            '.box_dump/index.php',
        ];

        $actualFiles = $this->normalizePaths(
            iterator_to_array(
                Finder::create()->files()->in('.box_dump')->ignoreDotFiles(false),
                true,
            ),
        );

        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);

        $expectedDumpedConfig = <<<'EOF'
            //
            // Processed content of the configuration file "/path/to/box.json" dumped for debugging purposes
            //
            // PHP Version: 10.0.0
            // PHP extensions: Core,date
            // OS: Darwin / 17.7.0
            // Command: bin/phpunit
            // Box: x.x-dev@27df576
            // Time: 2018-05-24T20:59:15+00:00
            //

            KevinGH\Box\Configuration\ExportableConfiguration {#140
              -file: "box.json"
              -alias: "index.phar"
              -basePath: "/path/to"
              -composerJson: null
              -composerLock: null
              -files: []
              -binaryFiles: []
              -autodiscoveredFiles: true
              -dumpAutoload: false
              -excludeComposerArtifacts: true
              -excludeDevFiles: false
              -compactors: []
              -compressionAlgorithm: "NONE"
              -fileMode: "0755"
              -mainScriptPath: "index.php"
              -mainScriptContents: """
                <?php\n
                \n
                declare(strict_types=1);\n
                \n
                echo 'Yo';\n
                """
              -fileMapper: KevinGH\Box\MapFile {#140
                -basePath: "/path/to"
                -map: []
              }
              -metadata: null
              -tmpOutputPath: "index.phar"
              -outputPath: "index.phar"
              -privateKeyPassphrase: null
              -privateKeyPath: null
              -promptForPrivateKey: false
              -processedReplacements: []
              -shebang: "#!/usr/bin/env php"
              -signingAlgorithm: "SHA512"
              -stubBannerContents: ""
              -stubBannerPath: null
              -stubPath: null
              -isInterceptFileFuncs: false
              -isStubGenerated: true
              -checkRequirements: false
              -warnings: []
              -recommendations: []
            }

            EOF;

        $actualDumpedConfig = VarDumperNormalizer::normalize(
            $this->tmp,
            FS::getFileContents('.box_dump/.box_configuration'),
        );

        // Replace objects IDs
        $actualDumpedConfig = preg_replace(
            '/ \{#\d{3,}/',
            ' {#140',
            $actualDumpedConfig,
        );

        // Replace the expected PHP version
        $actualDumpedConfig = str_replace(
            sprintf(
                'PHP Version: %s',
                PHP_VERSION,
            ),
            'PHP Version: 10.0.0',
            $actualDumpedConfig,
        );

        // Replace the expected PHP extensions
        $actualDumpedConfig = str_replace(
            sprintf(
                'PHP extensions: %s',
                implode(',', get_loaded_extensions()),
            ),
            'PHP extensions: Core,date',
            $actualDumpedConfig,
        );

        // Replace the expected OS version
        $actualDumpedConfig = str_replace(
            sprintf(
                'OS: %s / %s',
                PHP_OS,
                php_uname('r'),
            ),
            'OS: Darwin / 17.7.0',
            $actualDumpedConfig,
        );

        // Replace the expected command
        $actualDumpedConfig = str_replace(
            sprintf(
                'Command: %s',
                implode(' ', $GLOBALS['argv']),
            ),
            'Command: bin/phpunit',
            $actualDumpedConfig,
        );

        // Replace the expected Box version
        $actualDumpedConfig = str_replace(
            sprintf(
                'Box: %s',
                get_box_version(),
            ),
            'Box: x.x-dev@27df576',
            $actualDumpedConfig,
        );

        // Replace the expected time
        $actualDumpedConfig = preg_replace(
            '/Time: \d{4,}-\d{2,}-\d{2,}T\d{2,}:\d{2,}:\d{2,}\+\d{2,}:\d{2,}/',
            'Time: 2018-05-24T20:59:15+00:00',
            $actualDumpedConfig,
        );

        self::assertSame($expectedDumpedConfig, $actualDumpedConfig);

        // Checks one of the dumped file from the PHAR to ensure the encoding of the extracted file is correct
        self::assertStringEqualsFile(
            '.box_dump/index.php',
            $indexContents,
        );
    }

    public function test_it_can_build_a_phar_file_in_quiet_mode(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = self::getExpectedShebang();

        FS::dumpFile(
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
                    'algorithm' => 'OPENSSL',
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->commandTester->setInputs(['test']);
        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_QUIET,
            ],
        );

        $expected = '';

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );

        // Check PHAR content
        $pharContents = file_get_contents('test.phar');
        $shebang = preg_quote($shebang, '/');

        self::assertMatchesRegularExpression("/{$shebang}/", $pharContents);
        self::assertMatchesRegularExpression('/custom banner/', $pharContents);

        $phar = new Phar('test.phar');

        self::assertSame(['rand' => $rand], $phar->getMetadata());
    }

    public function test_it_can_build_a_phar_file_using_the_phar_default_stub(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = self::getExpectedShebang();

        FS::dumpFile(
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
                    'metadata' => ['rand' => random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => false,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->commandTester->setInputs(['test']);    // Set input for the passphrase
        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_can_build_a_phar_file_using_a_custom_stub(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = self::getExpectedShebang();

        FS::dumpFile(
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

                PHP,
        );

        FS::dumpFile(
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
                    'algorithm' => 'OPENSSL',
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => 'custom_stub',
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->commandTester->setInputs(['test']);    // Set input for the passphrase
        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );

        $phar = new Phar('test.phar');

        $actualStub = self::normalizeStub($phar->getStub());

        self::assertSame($stub, $actualStub);
    }

    public function test_it_can_build_a_phar_file_using_the_default_stub(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        FS::dumpFile(
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
                ],
            ),
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_cannot_build_a_phar_using_unreadable_files(): void
    {
        FS::touch('index.php');
        FS::touch('unreadable-file.php');
        FS::chmod('unreadable-file.php', 0);

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'files' => ['unreadable-file.php'],
                ],
            ),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^The path ".+?" is not readable\.$/');

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );
    }

    public function test_it_can_build_a_phar_overwriting_an_existing_one_in_verbose_mode(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir002', $this->tmp);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $version = get_box_version();

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Removing the existing PHAR "/path/to/tmp/test.phar"
            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? Setting replacement values
              + @name@: world
            ? No compactor to register
            ? Adding main file: /path/to/tmp/test.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_can_dump_the_autoloader(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        FS::rename('run.php', 'index.php');

        self::assertFileDoesNotExist($this->tmp.'/vendor/autoload.php');

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertMatchesRegularExpression(
            '/\? Dumping the Composer autoloader/',
            $this->commandTester->getDisplay(),
            'Expected the autoloader to be dumped',
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
            self::assertFileExists('phar://index.phar/'.$composerFile);
        }
    }

    public function test_it_can_build_a_phar_without_dumping_the_autoloader(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        FS::rename('run.php', 'index.php');

        self::assertFileDoesNotExist($this->tmp.'/vendor/autoload.php');

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'dump-autoload' => false,
                ],
            ),
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertMatchesRegularExpression(
            '/\? Skipping dumping the Composer autoloader/',
            $this->commandTester->getDisplay(),
            'Did not expect the autoloader to be dumped',
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
            self::assertFileDoesNotExist('phar://index.phar/'.$composerFile);
        }
    }

    public function test_it_can_dump_the_autoloader_and_exclude_the_composer_files(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        FS::rename('run.php', 'index.php');

        self::assertFileDoesNotExist($this->tmp.'/vendor/autoload.php');

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertMatchesRegularExpression(
            '/\? Removing the Composer dump artefacts/',
            $this->commandTester->getDisplay(),
            'Expected the composer files to be removed',
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
            self::assertFileExists('phar://index.phar/'.$composerFile);
        }

        $removedComposerFiles = [
            'composer.json',
            'composer.lock',
            'vendor/composer/installed.json',
        ];

        foreach ($removedComposerFiles as $composerFile) {
            self::assertFileDoesNotExist('phar://index.phar/'.$composerFile);
        }
    }

    public function test_it_can_dump_the_autoloader_and_keep_the_composer_files(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        FS::rename('run.php', 'index.php');

        FS::dumpFile(
            'box.json',
            json_encode(['exclude-composer-files' => false]),
        );

        self::assertFileDoesNotExist($this->tmp.'/vendor/autoload.php');

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertMatchesRegularExpression(
            '/\? Keep the Composer dump artefacts/',
            $this->commandTester->getDisplay(),
            'Expected the composer files to be kept',
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
            self::assertFileExists('phar://index.phar/'.$composerFile);
        }

        $removedComposerFiles = [
            'composer.json',
            // The following two files do not exists since there is no dependency, check BoxTest for a more complete
            // test regarding this feature
            // 'composer.lock',
            // 'vendor/composer/installed.json',
        ];

        foreach ($removedComposerFiles as $composerFile) {
            self::assertFileExists('phar://index.phar/'.$composerFile);
        }
    }

    public function test_it_can_build_a_phar_with_a_custom_banner(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir003', $this->tmp);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

                ____
               / __ )____  _  __
              / __  / __ \| |/_/
             / /_/ / /_/ />  <
            /_____/\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/test.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using custom banner from file: /path/to/tmp/banner
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_can_build_a_phar_with_a_stub_file(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

                ____
               / __ )____  _  __
              / __  / __ \| |/_/
             / /_/ / /_/ />  <
            /_____/\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/test.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Using stub file: /path/to/tmp/stub.php
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_can_build_a_phar_with_the_default_stub_file(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir005', $this->tmp);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $version = get_box_version();

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/index.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > 1 file(s)
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 2 files (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_can_build_a_phar_without_a_main_script(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

        FS::dumpFile(
            'box.json',
            <<<'JSON'
                {
                    "files-bin": ["test.php"],
                    "stub": "stub.php",
                    "main": false
                }
                JSON,
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

                ____
               / __ )____  _  __
              / __  / __ \| |/_/
             / /_/ / /_/ />  <
            /_____/\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? No main script path configured
            ? Skip requirements checker
            ? Adding binary files
                > 1 file(s)
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Using stub file: /path/to/tmp/stub.php
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_can_build_an_empty_phar(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

        FS::dumpFile(
            'box.json',
            <<<'JSON'
                {
                    "stub": "stub.php",
                    "main": false
                }
                JSON,
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

                ____
               / __ )____  _  __
              / __  / __ \| |/_/
             / /_/ / /_/ />  <
            /_____/\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? No main script path configured
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Using stub file: /path/to/tmp/stub.php
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected PHAR to be executable',
        );

        $expectedFiles = [
            '/.box_empty',
        ];

        $actualFiles = $this->retrievePharFiles(new Phar('test.phar'));

        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_phar_with_compressed_code(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir006', $this->tmp);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $version = get_box_version();

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/test.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? Compressing with the algorithm "GZ"
                > Warning: the extension "zlib" will now be required to execute the PHAR
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        $builtPhar = new Phar('test.phar');

        self::assertFalse($builtPhar->isCompressed()); // This is a bug, see https://github.com/humbug/box/issues/20
        self::assertTrue($builtPhar['test.php']->isCompressed());

        self::assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected the PHAR to be executable',
        );
    }

    public function test_it_can_build_a_phar_in_a_non_existent_directory(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir007', $this->tmp);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $version = get_box_version();

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/foo/bar/test.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/test.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello!',
            exec('php foo/bar/test.phar'),
            'Expected the PHAR to be executable',
        );
    }

    #[DataProvider('aliasConfigProvider')]
    public function test_it_configures_the_phar_alias(bool $stub): void
    {
        $this->skipIfDefaultStubNotFound();
        FS::mirror(self::FIXTURES_DIR.'/dir008', $this->tmp);

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'alias' => $alias = 'alias-test.phar',
                    'main' => 'index.php',
                    'stub' => $stub,
                    'blacklist' => ['box.json'],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            0,
            $this->commandTester->getStatusCode(),
            sprintf(
                'Expected the command to successfully run. Got: %s',
                $this->commandTester->getDisplay(),
            ),
        );

        self::assertSame(
            '',
            exec('php index.phar'),
            'Expected PHAR to be executable',
        );

        $phar = new Phar('index.phar');

        // Check the stub content
        $actualStub = self::normalizeStub($phar->getStub());
        $defaultStub = self::normalizeStub(file_get_contents(self::FIXTURES_DIR.'/../../dist/default_stub.php'));

        if ($stub) {
            self::assertSame($phar->getPath(), $phar->getAlias());

            self::assertDoesNotMatchRegularExpression(
                '/Phar::webPhar\(.*\);/',
                $actualStub,
            );
            self::assertMatchesRegularExpression(
                '/Phar::mapPhar\(\'alias-test\.phar\'\);/',
                $actualStub,
            );
        } else {
            self::assertSame($alias, $phar->getAlias());

            self::assertSame($defaultStub, $actualStub);

            // No alias is found: I find it weird but well, that's the default stub so there is not much that can
            // be done here. Maybe there is a valid reason I'm not aware of.
            self::assertDoesNotMatchRegularExpression(
                '/alias-test\.phar/',
                $actualStub,
            );
        }

        $expectedFiles = [
            '/index.php',
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_phar_file_without_a_shebang_line(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir006', $this->tmp);

        $boxRawConfig = json_decode(file_get_contents('box.json'), true, 512, JSON_PRETTY_PRINT);
        $boxRawConfig['shebang'] = false;
        FS::dumpFile('box.json', json_encode($boxRawConfig, JSON_PRETTY_PRINT));

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $version = get_box_version();

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/test.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Generating new stub
              - No shebang line
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? Compressing with the algorithm "GZ"
                > Warning: the extension "zlib" will now be required to execute the PHAR
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        $builtPhar = new Phar('test.phar');

        self::assertFalse($builtPhar->isCompressed()); // This is a bug, see https://github.com/humbug/box/issues/20
        self::assertTrue($builtPhar['test.php']->isCompressed());

        self::assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected the PHAR to be executable',
        );
    }

    public function test_it_can_build_a_phar_with_an_output_which_does_not_have_a_phar_extension(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

        FS::dumpFile(
            'box.json',
            json_encode(
                array_merge(
                    json_decode(
                        file_get_contents('box.json'),
                        true,
                        512,
                        JSON_THROW_ON_ERROR,
                    ),
                    ['output' => 'test'],
                ),
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<'OUTPUT'

                ____
               / __ )____  _  __
              / __  / __ \| |/_/
             / /_/ / /_/ />  <
            /_____/\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/test"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/test.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Using stub file: /path/to/tmp/stub.php
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        self::assertSame(
            'Hello!',
            exec('php test'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_can_build_a_phar_ignoring_the_configuration(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir009', $this->tmp);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
                '--no-config' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Index',
            exec('php index.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_ignores_the_config_given_when_the_no_config_setting_is_set(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir009', $this->tmp);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
                '--config' => 'box.json',
                '--no-config' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Index',
            exec('php index.phar'),
            'Expected PHAR to be executable',
        );
    }

    public function test_it_can_build_a_phar_with_a_php_scoper_config(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir010', $this->tmp);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Index',
            exec('php index.phar'),
            'Expected PHAR to be executable',
        );

        self::assertSame(
            1,
            preg_match(
                '/namespace (?<namespace>.*);/',
                (string) ($indexContents = file_get_contents('phar://index.phar/index.php')),
                $matches,
            ),
            sprintf(
                'Expected the content of the PHAR index.php file to match the given regex. The following '
                .'contents does not: "%s"',
                $indexContents,
            ),
        );

        $phpScoperNamespace = $matches['namespace'];

        self::assertStringStartsWith('_HumbugBox', $phpScoperNamespace);
    }

    public function test_it_can_build_a_phar_with_a_php_scoper_config_with_a_specific_prefix(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir010', $this->tmp);

        FS::rename('scoper-fixed-prefix.inc.php', 'scoper.inc.php', true);

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        self::assertSame(
            'Index',
            exec('php index.phar'),
            'Expected PHAR to be executable',
        );

        self::assertSame(
            1,
            preg_match(
                '/namespace (?<namespace>.*);/',
                (string) ($indexContents = file_get_contents('phar://index.phar/index.php')),
                $matches,
            ),
            sprintf(
                'Expected the content of the PHAR index.php file to match the given regex. The following '
                .'contents does not: "%s"',
                $indexContents,
            ),
        );

        $phpScoperNamespace = $matches['namespace'];

        self::assertSame('Acme', $phpScoperNamespace);
    }

    public function test_it_cannot_sign_a_phar_with_the_openssl_algorithm_without_a_private_key(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir010', $this->tmp);

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'algorithm' => 'OPENSSL',
                ],
            ),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected to have a private key for OpenSSL signing but none have been provided.');

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );
    }

    public function test_it_displays_recommendations_and_warnings(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir010', $this->tmp);

        FS::remove('composer.json');

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'check-requirements' => true,
                ],
            ),
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
            ],
            ['interactive' => true],
        );

        $version = get_box_version();

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/index.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/index.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            💡  <recommendation>1 recommendation found:</recommendation>
                - The "check-requirements" setting can be omitted since is set to its default value
            ⚠️  <warning>1 warning found:</warning>
                - The requirement checker could not be used because the composer.json and composer.lock file could not be found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_skips_the_compression_when_in_dev_mode(): void
    {
        FS::mirror(self::FIXTURES_DIR.'/dir010', $this->tmp);

        FS::dumpFile(
            'box.json',
            json_encode(
                [
                    'compression' => 'GZ',
                ],
            ),
        );

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
                '--dev' => null,
            ],
            ['interactive' => true],
        );

        $version = get_box_version();

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/index.phar"

            ? Skipping the Composer compatibility check: the autoloader is not dumped
            ? No compactor to register
            ? Adding main file: /path/to/tmp/index.php
            ? Skip requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? No
            ? Adding files
                > No file found
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Skipping dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? Dev mode detected: skipping the compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: 1 file (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_can_generate_a_phar_with_docker(): void
    {
        if (extension_loaded('xdebug')) {
            self::markTestSkipped('Skipping this test since xdebug has an include wrapper causing this test to fail');
        }

        FS::mirror(self::FIXTURES_DIR.'/dir010', $this->tmp);

        FS::dumpFile('box.json', '{}');
        FS::dumpFile('composer.json', '{}');

        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--no-parallel' => null,
                '--with-docker' => null,
            ],
            ['interactive' => true],
        );

        $version = get_box_version();
        $expectedNumberOfFiles = self::NUMBER_OF_FILES - 4;

        $expected = <<<OUTPUT

                ____
               / __ )____  _  __
              / __  / __ \\| |/_/
             / /_/ / /_/ />  <
            /_____/\\____/_/|_|


            Box version x.x-dev@151e40a

             // Loading the configuration file "/path/to/box.json.dist".

            🔨  Building the PHAR "/path/to/tmp/index.phar"

            ? Checking Composer compatibility
                > Supported version detected
            ? No compactor to register
            ? Adding main file: /path/to/tmp/index.php
            ? Adding requirements checker
            ? Adding binary files
                > No file found
            ? Auto-discover files? Yes
            ? Exclude dev files? Yes
            ? Adding files
                > 1 file(s)
            ? Generating new stub
              - Using shebang line: #!/usr/bin/env php
              - Using banner:
                > Generated by Humbug Box {$version}.
                >
                > @link https://github.com/humbug/box
            ? Dumping the Composer autoloader
            ? Removing the Composer dump artefacts
            ? No compression
            ? Setting file permissions to 0755
            * Done.

            No recommendation found.
            No warning found.

             // PHAR: {$expectedNumberOfFiles} files (100B)
             // You can inspect the generated PHAR with the "info" command.

             // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


             // Loading the configuration file "/path/to/box.json.dist".


            🐳  Generating a Dockerfile for the PHAR "/path/to/tmp/index.phar"

             [OK] Done

            You can now inspect your Dockerfile file or build your container with:
            $ docker build .

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public static function aliasConfigProvider(): iterable
    {
        yield [true];
        yield [false];
    }

    private function createCompilerDisplayNormalizer(): callable
    {
        $tmp = $this->tmp;

        return static function (string $output) use ($tmp): string {
            $output = str_replace($tmp, '/path/to/tmp', $output);

            $output = preg_replace(
                '/Loading the configuration file[\s\n]+.*[\s\n\/]+.*box\.json[comment\<\>\n\s\/]*"\./',
                'Loading the configuration file "/path/to/box.json.dist".',
                $output,
            );

            $output = preg_replace(
                '/You can inspect the generated PHAR( | *\n *\/\/ *)with( | *\n *\/\/ *)the( | *\n *\/\/ *)"info"( | *\n *\/\/ *)command/',
                'You can inspect the generated PHAR with the "info" command',
                $output,
            );

            $output = preg_replace(
                '/\/\/ PHAR: (\d+ files?) \(\d+\.\d{2}K?B\)/',
                '// PHAR: $1 (100B)',
                $output,
            );

            $output = preg_replace(
                '/\/\/ Memory usage: \d+\.\d{2}MB \(peak: \d+\.\d{2}MB\), time: .*?secs?/',
                '// Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s',
                $output,
            );

            $output = str_replace(
                'Xdebug',
                'xdebug',
                $output,
            );

            $output = preg_replace(
                '/\[debug\] Increased the maximum number of open file descriptors from \([^\)]+\) to \([^\)]+\)'.PHP_EOL.'/',
                '',
                $output,
            );

            $output = str_replace(
                '[debug] Restored the maximum number of open file descriptors'.PHP_EOL,
                '',
                $output,
            );

            if (extension_loaded('xdebug')) {
                $output = preg_replace(
                    '/'.PHP_EOL.'You are running composer with xdebug enabled. This has a major impact on runtime performance. See https:\/[^\s]+'.PHP_EOL.'/',
                    '',
                    $output,
                );
            }

            return $output;
        };
    }

    private static function createComposerPathNormalizer(): callable
    {
        return static fn (string $output): string => preg_replace(
            '/(\/.*?composer)/',
            '/usr/local/bin/composer',
            $output,
        );
    }

    private static function createComposerVersionNormalizer(): callable
    {
        return static fn (string $output): string => preg_replace(
            '/> Version detected: ([\d.]+) \(Box requires \^2\.2\.0\)/',
            '> Version detected: 2.2.22 (Box requires ^2.2.0)',
            $output,
        );
    }

    private function retrievePharFiles(Phar $phar, ?Traversable $traversable = null): array
    {
        $root = 'phar://'.str_replace('\\', '/', realpath($phar->getPath())).'/';

        if (null === $traversable) {
            $traversable = $phar;
        }

        $collectedPaths = [];

        foreach ($traversable as $fileInfo) {
            /** @var PharFileInfo $fileInfo */
            $fileInfo = $phar[str_replace($root, '', $fileInfo->getPathname())];

            $path = mb_substr($fileInfo->getPathname(), mb_strlen($root) - 1);

            if ($fileInfo->isDir()) {
                $path .= '/';

                $collectedPaths[] = $this->retrievePharFiles(
                    $phar,
                    new DirectoryIterator($fileInfo->getPathname()),
                );
            }

            $collectedPaths[] = [$path];
        }

        $paths = array_merge(...$collectedPaths);
        sort($paths);

        return array_unique($paths);
    }

    private static function normalizeStub(string $pharStub): string
    {
        return preg_replace(
            '/box-auto-generated-alias-[\da-zA-Z]{12}\.phar/',
            'box-auto-generated-alias-__uniqid__.phar',
            DisplayNormalizer::removeTrailingSpaces($pharStub),
        );
    }

    /**
     * @param callable(string):string $extraNormalizers
     */
    private function assertSameOutput(
        string $expectedOutput,
        int $expectedStatusCode,
        callable ...$extraNormalizers,
    ): void {
        OutputAssertions::assertSameOutput(
            $expectedOutput,
            $expectedStatusCode,
            $this->commandTester,
            BoxDisplayNormalizer::createReplaceBoxVersionNormalizer(),
            $this->createCompilerDisplayNormalizer(),
            self::createComposerPathNormalizer(),
            self::createComposerVersionNormalizer(),
            ...$extraNormalizers,
        );
    }

    private function skipIfDefaultStubNotFound(): void
    {
        if (!file_exists(self::DEFAULT_STUB_PATH)) {
            self::markTestSkipped('The default stub file could not be found. Run the tests via the make commands or manually generate the stub file with `$ make generate_default_stub`.');
        }
    }

    private static function getExpectedShebang(): string
    {
        return sprintf('#!%s', (new PhpExecutableFinder())->find());
    }
}
