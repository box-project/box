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
use Fidry\Console\Test\OutputAssertions;
use function getenv;
use function implode;
use InvalidArgumentException;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use function preg_replace;
use function realpath;
use function str_replace;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

/**
 * @covers \KevinGH\Box\Console\Command\Info
 *
 * @runTestsInSeparateProcesses This is necessary as instantiating a PHAR in memory may load/autoload some stuff which
 *                              can create undesirable side-effects.
 */
class InfoTest extends CommandTestCase
{
    private const FIXTURES = __DIR__.'/../../../fixtures/info';

    protected function getCommand(): Command
    {
        return new Info();
    }

    public function test_it_provides_info_about_the_phar_api(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'info',
            ],
        );

        $version = Phar::apiVersion();
        $compression = '  - '.implode("\n  - ", Phar::getSupportedCompression());
        $signatures = '  - '.implode("\n  - ", Phar::getSupportedSignatures());

        $expected = <<<OUTPUT

            API Version: $version

            Supported Compression:
            $compression

            Supported Signatures:
            $signatures

             // Get a PHAR details by giving its path as an argument.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_provides_info_about_a_phar(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.phar';
        $phar = new Phar($pharPath);

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
            ],
        );

        $expected = <<<OUTPUT

            API Version: $version

            Compression: None

            Signature: {$signature['hash_type']}
            Signature Hash: {$signature['hash']}

            Metadata: None

            Contents: 1 file (6.61KB)

             // Use the --list|-l option to list the content of the PHAR.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_provides_info_about_a_phar_without_extension(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar';
        $phar = new Phar($pharPath.'.phar');

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
            ],
        );

        $expected = <<<OUTPUT

            API Version: $version

            Compression: None

            Signature: {$signature['hash_type']}
            Signature Hash: {$signature['hash']}

            Metadata: None

            Contents: 1 file (6.61KB)

             // Use the --list|-l option to list the content of the PHAR.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_cannot_provide_info_about_an_invalid_phar_without_extension(): void
    {
        if ('v3' === getenv('SYMFONY_VERSION')) {
            $this->markTestSkipped();
        }

        $file = self::FIXTURES.'/foo';

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $file,
            ],
        );

        $expectedPath = realpath($file);

        $expected = <<<OUTPUT


             [ERROR] Could not read the file "$expectedPath".


            OUTPUT;

        $this->assertSameOutput(
            $expected,
            ExitCode::FAILURE,
            static fn ($output) => preg_replace('/file[\ \n]+"/', 'file "', $output),
        );
    }

    public function test_it_displays_the_error_in_debug_verbosity(): void
    {
        $file = self::FIXTURES.'/foo';

        try {
            $this->commandTester->execute(
                [
                    'command' => 'info',
                    'phar' => $file,
                ],
                ['verbosity' => OutputInterface::VERBOSITY_DEBUG],
            );

            $this->fail('Expected exception to be thrown.');
        } catch (UnexpectedValueException $exception) {
            $this->assertStringStartsWith('Cannot create phar', $exception->getMessage());
        }
    }

    public function test_it_provides_info_about_a_targz_phar(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.tar.gz';

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
            ],
        );

        $expected = <<<'OUTPUT'

            API Version: No information found

            Compression: GZ

            Signature unreadable

            Metadata: None

            Contents: 1 file (2.56KB)

             // Use the --list|-l option to list the content of the PHAR.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_provides_info_about_a_tarbz2_phar(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.tar.bz2';

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
            ],
        );

        $expected = <<<'OUTPUT'

            API Version: No information found

            Compression: BZ2

            Signature unreadable

            Metadata: None

            Contents: 1 file (2.71KB)

             // Use the --list|-l option to list the content of the PHAR.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_provides_a_zip_phar_info(): void
    {
        if ('v3' === getenv('SYMFONY_VERSION')) {
            $this->markTestSkipped();
        }

        $pharPath = self::FIXTURES.'/new-simple-phar.zip';

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
            ],
        );

        $expected = <<<'OUTPUT'


             [ERROR] Could not read the file "new-simple-phar.zip".


            OUTPUT;

        OutputAssertions::assertSameOutput(
            $expected,
            ExitCode::FAILURE,
            $this->commandTester,
            static fn ($output) => preg_replace(
                '/\s\[ERROR\] Could not read the file([\s\S]*)new\-simple\-phar\.zip[comment\<\>\n\s\/]*"\./',
                ' [ERROR] Could not read the file "new-simple-phar.zip".',
                $output,
            ),
        );
    }

    public function test_it_provides_a_phar_info_with_the_tree_of_the_content(): void
    {
        $pharPath = self::FIXTURES.'/tree-phar.phar';
        $phar = new Phar($pharPath);

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
                '--list' => true,
                '--metadata' => true,
            ],
        );

        $expected = <<<OUTPUT

            API Version: $version

            Compression:
              - BZ2 (33.33%)
              - None (66.67%)

            Signature: {$signature['hash_type']}
            Signature Hash: {$signature['hash']}

            Metadata:
            array (
              'test' => 123,
            )

            Contents: 3 files (6.75KB)
            a/
              bar.php [BZ2] - 60.00B
            b/
              beta/
                bar.php [NONE] - 0.00B
            foo.php [NONE] - 19.00B

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_provides_a_phar_info_with_the_flat_tree_of_the_content(): void
    {
        $pharPath = self::FIXTURES.'/tree-phar.phar';
        $phar = new Phar($pharPath);

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
                '--list' => true,
                '--mode' => 'flat',
            ],
        );

        $expected = <<<OUTPUT

            API Version: $version

            Compression:
              - BZ2 (33.33%)
              - None (66.67%)

            Signature: {$signature['hash_type']}
            Signature Hash: {$signature['hash']}

            Metadata:
            array (
              'test' => 123,
            )

            Contents: 3 files (6.75KB)
            a/bar.php [BZ2] - 60.00B
            b/beta/bar.php [NONE] - 0.00B
            foo.php [NONE] - 19.00B

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_provides_a_phar_info_with_the_tree_of_the_content_including_hidden_files(): void
    {
        $pharPath = self::FIXTURES.'/hidden-files.phar';
        $phar = new Phar($pharPath);

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
                '--list' => true,
            ],
        );

        $expected = <<<OUTPUT

            API Version: $version

            Compression: None

            Signature: {$signature['hash_type']}
            Signature Hash: {$signature['hash']}

            Metadata: None

            Contents: 16 files (7.50KB)
            .hidden-dir/
              .hidden-file1 [NONE] - 0.00B
              .hidden-file1.php [NONE] - 33.00B
              file1 [NONE] - 0.00B
              file1.php [NONE] - 33.00B
            .hidden-foo [NONE] - 0.00B
            .hidden-foo.php [NONE] - 33.00B
            a/
              .hidden-bar [NONE] - 0.00B
              .hidden-bar.php [NONE] - 33.00B
              .hidden-dir-2/
                .hidden-file2 [NONE] - 0.00B
                .hidden-file2.php [NONE] - 33.00B
                file2 [NONE] - 0.00B
                file2.php [NONE] - 33.00B
              bar [NONE] - 0.00B
              bar.php [NONE] - 33.00B
            foo [NONE] - 0.00B
            foo.php [NONE] - 33.00B

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    /**
     * @dataProvider treeDepthProvider
     */
    public function test_it_can_limit_the_tree_depth(
        string $pharPath,
        ?string $depth,
        mixed $expected,
    ): void {
        $pharPath = self::FIXTURES.'/tree-phar.phar';
        $phar = new Phar($pharPath);

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $expected = str_replace(
            [
                '__VERSION__',
                '__SIGNATURE__',
                '__SIGNATURE_HASH__',
            ],
            [
                $version,
                $signature['hash_type'],
                $signature['hash'],
            ],
            (string) $expected,
        );

        $input = [
            'command' => 'info',
            'phar' => $pharPath,
            '--list' => true,
            '--metadata' => true,
            '--depth' => $depth,
        ];

        if (null === $depth) {
            unset($input['--depth']);
        }

        $this->commandTester->execute($input);

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public static function treeDepthProvider(): iterable
    {
        $pharPath = self::FIXTURES.'/tree-phar.phar';

        yield 'depth=0' => [
            $pharPath,
            '0',
            <<<'OUTPUT'

                API Version: __VERSION__

                Compression:
                  - BZ2 (33.33%)
                  - None (66.67%)

                Signature: __SIGNATURE__
                Signature Hash: __SIGNATURE_HASH__

                Metadata:
                array (
                  'test' => 123,
                )

                Contents: 3 files (6.75KB)
                a/
                b/
                foo.php [NONE] - 19.00B

                OUTPUT,
        ];

        yield 'depth=1' => [
            $pharPath,
            '1',
            <<<'OUTPUT'

                API Version: __VERSION__

                Compression:
                  - BZ2 (33.33%)
                  - None (66.67%)

                Signature: __SIGNATURE__
                Signature Hash: __SIGNATURE_HASH__

                Metadata:
                array (
                  'test' => 123,
                )

                Contents: 3 files (6.75KB)
                a/
                  bar.php [BZ2] - 60.00B
                b/
                  beta/
                foo.php [NONE] - 19.00B

                OUTPUT,
        ];

        yield 'default depth, defined explicitly' => [
            $pharPath,
            '-1',
            <<<'OUTPUT'

                API Version: __VERSION__

                Compression:
                  - BZ2 (33.33%)
                  - None (66.67%)

                Signature: __SIGNATURE__
                Signature Hash: __SIGNATURE_HASH__

                Metadata:
                array (
                  'test' => 123,
                )

                Contents: 3 files (6.75KB)
                a/
                  bar.php [BZ2] - 60.00B
                b/
                  beta/
                    bar.php [NONE] - 0.00B
                foo.php [NONE] - 19.00B

                OUTPUT,
        ];

        yield 'default depth' => [
            $pharPath,
            null,
            <<<'OUTPUT'

                API Version: __VERSION__

                Compression:
                  - BZ2 (33.33%)
                  - None (66.67%)

                Signature: __SIGNATURE__
                Signature Hash: __SIGNATURE_HASH__

                Metadata:
                array (
                  'test' => 123,
                )

                Contents: 3 files (6.75KB)
                a/
                  bar.php [BZ2] - 60.00B
                b/
                  beta/
                    bar.php [NONE] - 0.00B
                foo.php [NONE] - 19.00B

                OUTPUT,
        ];
    }

    public function test_it_can_limit_the_tree_depth_in_flat_mode(): void
    {
        $pharPath = self::FIXTURES.'/tree-phar.phar';
        $phar = new Phar($pharPath);

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
                '--list' => true,
                '--metadata' => true,
                '--depth' => '1',
                '--mode' => 'flat',
            ],
        );

        $expected = <<<OUTPUT

            API Version: $version

            Compression:
              - BZ2 (33.33%)
              - None (66.67%)

            Signature: {$signature['hash_type']}
            Signature Hash: {$signature['hash']}

            Metadata:
            array (
              'test' => 123,
            )

            Contents: 3 files (6.75KB)
            a/bar.php [BZ2] - 60.00B
            foo.php [NONE] - 19.00B

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_cannot_accept_an_invalid_depth(): void
    {
        $pharPath = self::FIXTURES.'/tree-phar.phar';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the depth to be a positive integer or -1: "-10".');

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
                '--list' => true,
                '--metadata' => true,
                '--depth' => '-10',
            ],
        );
    }
}
