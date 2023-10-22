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
use InvalidArgumentException;
use KevinGH\Box\Phar\InvalidPhar;
use KevinGH\Box\Platform;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use Symfony\Component\Console\Output\OutputInterface;
use function extension_loaded;
use function implode;

/**
 * @covers \KevinGH\Box\Console\Command\Info
 * @covers \KevinGH\Box\Console\Command\PharInfoRenderer
 *
 * @internal
 */
class InfoTest extends CommandTestCase
{
    private const FIXTURES = __DIR__.'/../../../fixtures/info';

    protected function setUp(): void
    {
        if (!Platform::isOSX()) {
            self::markTestSkipped('This test requires more work to be working fine cross-platform.');
        }

        parent::setUp();
    }

    protected function getCommand(): Command
    {
        return new Info();
    }

    /**
     * @dataProvider inputProvider
     */
    public function test_it_provides_info_about_the_phar_extension_or_the_given_phar_archive(
        array $input,
        string $expected,
    ): void {
        $input['command'] = 'info';

        $this->commandTester->execute($input);

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public static function inputProvider(): iterable
    {
        yield 'PHAR extension data' => (static function (): array {
            $version = Phar::apiVersion();
            $compression = '  - '.implode("\n  - ", Phar::getSupportedCompression());
            $signatures = '  - '.implode("\n  - ", Phar::getSupportedSignatures());

            return [
                [],
                <<<OUTPUT

                    API Version: {$version}

                    Supported Compression:
                    {$compression}

                    Supported Signatures:
                    {$signatures}

                     // Get a PHAR details by giving its path as an argument.


                    OUTPUT,
            ];
        })();

        yield 'simple non-compressed PHAR' => [
            [
                'phar' => self::FIXTURES.'/../phar/simple-phar.phar',
            ],
            <<<'OUTPUT'

                API Version: 1.1.0

                Archive Compression: None
                Files Compression: None

                Signature: SHA-256
                Signature Hash: 55AE0CCD6D3A74BE41E19CD070A655A73FEAEF8342084A0801954943FBF219ED

                Metadata: None

                Timestamp: 1680285013 (2023-03-31T17:50:13+00:00)

                Contents: 1 file (6.62KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];

        if (extension_loaded('zlib')) {
            yield 'simple GZ-compressed PHAR' => [
                [
                    'phar' => self::FIXTURES.'/../extract/gz-compressed-phar.phar',
                ],
                <<<'OUTPUT'

                    API Version: 1.1.0

                    Archive Compression: None
                    Files Compression: GZ

                    Signature: SHA-1
                    Signature Hash: 3CCDA01B80C1CAC91494EA59BBAFA479E38CD120

                    Metadata: None

                    Timestamp: 1559807994 (2019-06-06T07:59:54+00:00)

                    Contents: 2 files (6.61KB)

                     // Use the --list|-l option to list the content of the PHAR.


                    OUTPUT,
            ];
        }

        yield 'non PHAR archive' => [
            [
                'phar' => self::FIXTURES.'/../phar/simple.tar',
            ],
            <<<'OUTPUT'

                API Version: No information found

                Archive Compression: None
                Files Compression: None

                Signature unreadable

                Metadata: None

                Timestamp: 1680284754 (2023-03-31T17:45:54+00:00)

                Contents: 1 file (2.00KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];

        yield 'non PHAR archive with files listed' => [
            [
                'phar' => self::FIXTURES.'/../phar/simple.tar',
                '--list' => null,
            ],
            <<<'OUTPUT'

                API Version: No information found

                Archive Compression: None
                Files Compression: None

                Signature unreadable

                Metadata: None

                Timestamp: 1680284754 (2023-03-31T17:45:54+00:00)

                Contents: 1 file (2.00KB)
                sample.txt [NONE] - 13.00B

                OUTPUT,
        ];

        if (extension_loaded('zlib')) {
            yield 'simple TAR-GZ file' => [
                [
                    'phar' => self::FIXTURES.'/simple-phar.tar.gz',
                ],
                <<<'OUTPUT'

                    API Version: No information found

                    Archive Compression: GZ
                    Files Compression: None

                    Signature unreadable

                    Metadata: None

                    Timestamp: 1509920675 (2017-11-05T22:24:35+00:00)

                    Contents: 1 file (2.56KB)

                     // Use the --list|-l option to list the content of the PHAR.


                    OUTPUT,
            ];
        }

        yield 'PHAR with a complete tree files' => [
            [
                'phar' => self::FIXTURES.'/complete-tree.phar',
            ],
            <<<'OUTPUT'

                API Version: 1.1.0

                Archive Compression: None
                Files Compression: None

                Signature: SHA-256
                Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                Metadata: None

                Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                Contents: 13 files (7.09KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];

        if (Platform::isOSX()) {
            yield 'list PHAR files (OSX)' => [
                [
                    'phar' => self::FIXTURES.'/complete-tree.phar',
                    '--list' => null,
                ],
                <<<'OUTPUT'

                    API Version: 1.1.0

                    Archive Compression: None
                    Files Compression: None

                    Signature: SHA-256
                    Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                    Metadata: None

                    Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                    Contents: 13 files (7.09KB)
                    fileX [NONE] - 0.00B
                    .hidden-file [NONE] - 0.00B
                    .hidden-dir/
                      fileY [NONE] - 0.00B
                      dir/
                        fileZ [NONE] - 0.00B
                      .hidden-file [NONE] - 0.00B
                    dir1/
                      fileG [NONE] - 0.00B
                      fileH [NONE] - 0.00B
                    dir0/
                      fileB [NONE] - 0.00B
                      dir01/
                        fileD [NONE] - 0.00B
                        fileC [NONE] - 0.00B
                      fileA [NONE] - 0.00B
                      dir02/
                        dir020/
                          fileE [NONE] - 0.00B
                          fileF [NONE] - 0.00B

                    OUTPUT,
            ];
        } else {
            yield 'list PHAR files' => [
                [
                    'phar' => self::FIXTURES.'/complete-tree.phar',
                    '--list' => null,
                ],
                <<<'OUTPUT'

                    API Version: 1.1.0

                    Archive Compression: None
                    Files Compression: None

                    Signature: SHA-256
                    Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                    Metadata: None

                    Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                    Contents: 13 files (7.09KB)
                    fileX [NONE] - 0.00B
                    dir0/
                      fileA [NONE] - 0.00B
                      dir02/
                        dir020/
                          fileE [NONE] - 0.00B
                          fileF [NONE] - 0.00B
                      fileB [NONE] - 0.00B
                      dir01/
                        fileD [NONE] - 0.00B
                        fileC [NONE] - 0.00B
                    .hidden-dir/
                      fileY [NONE] - 0.00B
                      .hidden-file [NONE] - 0.00B
                      dir/
                        fileZ [NONE] - 0.00B
                    .hidden-file [NONE] - 0.00B
                    dir1/
                      fileG [NONE] - 0.00B
                      fileH [NONE] - 0.00B

                    OUTPUT,
            ];
        }

        if (Platform::isOSX()) {
            yield 'list PHAR files with limited depth (OSX)' => [
                [
                    'phar' => self::FIXTURES.'/complete-tree.phar',
                    '--list' => null,
                    '--depth' => '1',
                ],
                <<<'OUTPUT'

                    API Version: 1.1.0

                    Archive Compression: None
                    Files Compression: None

                    Signature: SHA-256
                    Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                    Metadata: None

                    Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                    Contents: 13 files (7.09KB)
                    fileX [NONE] - 0.00B
                    .hidden-file [NONE] - 0.00B
                    .hidden-dir/
                      fileY [NONE] - 0.00B
                      dir/
                        fileZ [NONE] - 0.00B

                    OUTPUT,
            ];
        } else {
            yield 'list PHAR files with limited depth' => [
                [
                    'phar' => self::FIXTURES.'/complete-tree.phar',
                    '--list' => null,
                    '--depth' => '1',
                ],
                <<<'OUTPUT'

                    API Version: 1.1.0

                    Archive Compression: None
                    Files Compression: None

                    Signature: SHA-256
                    Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                    Metadata: None

                    Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                    Contents: 13 files (7.09KB)
                    fileX [NONE] - 0.00B
                    dir0/
                      fileA [NONE] - 0.00B
                      dir02/
                        dir020/
                          fileE [NONE] - 0.00B

                    OUTPUT,
            ];
        }

        if (Platform::isOSX()) {
            yield 'list PHAR files with no indent (OSX)' => [
                [
                    'phar' => self::FIXTURES.'/complete-tree.phar',
                    '--list' => null,
                    '--mode' => 'flat',
                ],
                <<<'OUTPUT'

                    API Version: 1.1.0

                    Archive Compression: None
                    Files Compression: None

                    Signature: SHA-256
                    Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                    Metadata: None

                    Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                    Contents: 13 files (7.09KB)
                    fileX [NONE] - 0.00B
                    .hidden-file [NONE] - 0.00B
                    .hidden-dir/fileY [NONE] - 0.00B
                    .hidden-dir/dir/fileZ [NONE] - 0.00B
                    .hidden-dir/.hidden-file [NONE] - 0.00B
                    dir1/fileG [NONE] - 0.00B
                    dir1/fileH [NONE] - 0.00B
                    dir0/fileB [NONE] - 0.00B
                    dir0/dir01/fileD [NONE] - 0.00B
                    dir0/dir01/fileC [NONE] - 0.00B
                    dir0/fileA [NONE] - 0.00B
                    dir0/dir02/dir020/fileE [NONE] - 0.00B
                    dir0/dir02/dir020/fileF [NONE] - 0.00B

                    OUTPUT,
            ];
        } else {
            yield 'list PHAR files with no indent' => [
                [
                    'phar' => self::FIXTURES.'/complete-tree.phar',
                    '--list' => null,
                    '--mode' => 'flat',
                ],
                <<<'OUTPUT'

                    API Version: 1.1.0

                    Archive Compression: None
                    Files Compression: None

                    Signature: SHA-256
                    Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                    Metadata: None

                    Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                    Contents: 13 files (7.09KB)
                    fileX [NONE] - 0.00B
                    dir0/fileA [NONE] - 0.00B
                    dir0/dir02/dir020/fileE [NONE] - 0.00B
                    dir0/dir02/dir020/fileF [NONE] - 0.00B
                    dir0/fileB [NONE] - 0.00B
                    dir0/dir01/fileD [NONE] - 0.00B
                    dir0/dir01/fileC [NONE] - 0.00B
                    .hidden-dir/fileY [NONE] - 0.00B
                    .hidden-dir/.hidden-file [NONE] - 0.00B
                    .hidden-dir/dir/fileZ [NONE] - 0.00B
                    .hidden-file [NONE] - 0.00B
                    dir1/fileG [NONE] - 0.00B
                    dir1/fileH [NONE] - 0.00B

                    OUTPUT,
            ];
        }

        if (Platform::isOSX()) {
            yield 'list PHAR files with limited depth and no indent (OSX)' => [
                [
                    'phar' => self::FIXTURES.'/complete-tree.phar',
                    '--list' => null,
                    '--depth' => '1',
                    '--mode' => 'flat',
                ],
                <<<'OUTPUT'

                    API Version: 1.1.0

                    Archive Compression: None
                    Files Compression: None

                    Signature: SHA-256
                    Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                    Metadata: None

                    Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                    Contents: 13 files (7.09KB)
                    fileX [NONE] - 0.00B
                    .hidden-file [NONE] - 0.00B
                    .hidden-dir/fileY [NONE] - 0.00B
                    .hidden-dir/dir/fileZ [NONE] - 0.00B
                    .hidden-dir/.hidden-file [NONE] - 0.00B
                    dir1/fileG [NONE] - 0.00B
                    dir1/fileH [NONE] - 0.00B
                    dir0/fileB [NONE] - 0.00B
                    dir0/dir01/fileD [NONE] - 0.00B
                    dir0/dir01/fileC [NONE] - 0.00B
                    dir0/fileA [NONE] - 0.00B
                    dir0/dir02/dir020/fileE [NONE] - 0.00B
                    dir0/dir02/dir020/fileF [NONE] - 0.00B

                    OUTPUT,
            ];
        } else {
            yield 'list PHAR files with limited depth and no indent' => [
                [
                    'phar' => self::FIXTURES.'/complete-tree.phar',
                    '--list' => null,
                    '--depth' => '1',
                    '--mode' => 'flat',
                ],
                <<<'OUTPUT'

                    API Version: 1.1.0

                    Archive Compression: None
                    Files Compression: None

                    Signature: SHA-256
                    Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                    Metadata: None

                    Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                    Contents: 13 files (7.09KB)
                    fileX [NONE] - 0.00B
                    dir0/fileA [NONE] - 0.00B
                    dir0/dir02/dir020/fileE [NONE] - 0.00B
                    dir0/dir02/dir020/fileF [NONE] - 0.00B
                    dir0/fileB [NONE] - 0.00B
                    dir0/dir01/fileD [NONE] - 0.00B
                    dir0/dir01/fileC [NONE] - 0.00B
                    .hidden-dir/fileY [NONE] - 0.00B
                    .hidden-dir/.hidden-file [NONE] - 0.00B
                    .hidden-dir/dir/fileZ [NONE] - 0.00B
                    .hidden-file [NONE] - 0.00B
                    dir1/fileG [NONE] - 0.00B
                    dir1/fileH [NONE] - 0.00B

                    OUTPUT,
            ];
        }

        if (extension_loaded('zlib') && extension_loaded('bz2')) {
            if (Platform::isOSX()) {
                yield 'list PHAR files with various compressions (OSX)' => [
                    [
                        'phar' => self::FIXTURES.'/tree-phar.phar',
                        '--list' => null,
                    ],
                    <<<'OUTPUT'

                        API Version: 1.1.0

                        Archive Compression: None
                        Files Compression:
                          - BZ2 (33.33%)
                          - None (66.67%)

                        Signature: SHA-1
                        Signature Hash: 676AF6E890CA1C0EFDD1D856A944DF7FFAFEA06F

                        Metadata:
                        array (
                          'test' => 123,
                        )

                        Timestamp: 1527142573 (2018-05-24T06:16:13+00:00)

                        Contents: 3 files (6.75KB)
                        a/
                          bar.php [BZ2] - 60.00B
                        foo.php [NONE] - 19.00B
                        b/
                          beta/
                            bar.php [NONE] - 0.00B

                        OUTPUT,
                ];
            } else {
                yield 'list PHAR files with various compressions' => [
                    [
                        'phar' => self::FIXTURES.'/tree-phar.phar',
                        '--list' => null,
                    ],
                    <<<'OUTPUT'

                        API Version: 1.1.0

                        Archive Compression: None
                        Files Compression:
                          - BZ2 (33.33%)
                          - None (66.67%)

                        Signature: SHA-1
                        Signature Hash: 676AF6E890CA1C0EFDD1D856A944DF7FFAFEA06F

                        Metadata:
                        array (
                          'test' => 123,
                        )

                        Timestamp: 1527142573 (2018-05-24T06:16:13+00:00)

                        Contents: 3 files (6.75KB)
                        b/
                          beta/
                            bar.php [NONE] - 0.00B
                        a/
                          bar.php [BZ2] - 60.00B
                        foo.php [NONE] - 19.00B

                        OUTPUT,
                ];
            }
        }
    }

    public function test_it_cannot_provide_info_about_an_invalid_phar(): void
    {
        $file = self::FIXTURES.'/foo';

        $this->expectException(InvalidPhar::class);

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $file,
            ],
        );
    }

    public function test_it_displays_the_error_in_debug_verbosity(): void
    {
        $file = self::FIXTURES.'/foo';

        $this->expectException(InvalidPhar::class);

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $file,
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG],
        );
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
                '--depth' => '-10',
            ],
        );
    }

    public function test_it_cannot_accept_an_invalid_mode(): void
    {
        $pharPath = self::FIXTURES.'/tree-phar.phar';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected one of: "indent", "flat". Got: "smth" for the option "mode".');

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
                '--list' => true,
                '--mode' => 'smth',
            ],
        );
    }
}
