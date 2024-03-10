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
use KevinGH\Box\Console\PharInfoRenderer;
use KevinGH\Box\Phar\Throwable\InvalidPhar;
use KevinGH\Box\Platform;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\OutputInterface;
use function extension_loaded;
use function implode;

/**
 * @internal
 */
#[CoversClass(InfoCommand::class)]
#[CoversClass(PharInfoRenderer::class)]
class InfoCommandTest extends CommandTestCase
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
        return new InfoCommand();
    }

    #[DataProvider('inputProvider')]
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

                RequirementChecker: Not found.

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

                    RequirementChecker: Not found.

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

                RequirementChecker: Not found.

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

                RequirementChecker: Not found.

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

                    RequirementChecker: Not found.

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

                RequirementChecker: Not found.

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

                    RequirementChecker: Not found.

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

                    Built with Box: dev-main@b2c33cd

                    Archive Compression: None
                    Files Compression: None

                    Signature: SHA-256
                    Signature Hash: 5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414

                    Metadata: None

                    Timestamp: 1680645848 (2023-04-04T22:04:08+00:00)

                    RequirementChecker: Not found.

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

                    RequirementChecker: Not found.

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

                    RequirementChecker: Not found.

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

                    RequirementChecker: Not found.

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

                    RequirementChecker: Not found.

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

                    RequirementChecker: Not found.

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

                    RequirementChecker: Not found.

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

                        RequirementChecker: Not found.

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

                        RequirementChecker: Not found.

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

        yield 'PHAR with requirement checker; one format' => [
            ['phar' => self::FIXTURES.'/req-checker-old-req.phar'],
            <<<'OUTPUT'

                API Version: 1.1.0

                Built with Box: dev-main@b2c33cd

                Archive Compression: None
                Files Compression: None

                Signature: SHA-1
                Signature Hash: EEA3F86AA1B61484EE961055F43AA61805071CB1

                Metadata: None

                Timestamp: 1699390728 (2023-11-07T20:58:48+00:00)

                RequirementChecker:
                  Required:
                  - PHP >=5.3 (root)
                  - ext-phar (root)

                Contents: 47 files (147.97KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];

        yield 'PHAR with requirement checker; no requirements' => [
            ['phar' => self::FIXTURES.'/req-checker-empty.phar'],
            <<<'OUTPUT'

                API Version: 1.1.0

                Built with Box: dev-main@b7472c2

                Archive Compression: None
                Files Compression: None

                Signature: SHA-1
                Signature Hash: 2FA961D2CC4B35B6E0574C1A5082F79E9D9625E7

                Metadata: None

                Timestamp: 1697977482 (2023-10-22T12:24:42+00:00)

                RequirementChecker: No requirement found.

                Contents: 44 files (146.01KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];

        yield 'PHAR with requirement checker; one extension' => [
            ['phar' => self::FIXTURES.'/req-checker-ext.phar'],
            <<<'OUTPUT'

                API Version: 1.1.0

                Built with Box: dev-main@b2c33cd

                Archive Compression: None
                Files Compression: None

                Signature: SHA-1
                Signature Hash: A729D072C9F5B6242EBEE8DCFFFD2503C92B0AC3

                Metadata: None

                Timestamp: 1697989433 (2023-10-22T15:43:53+00:00)

                RequirementChecker:
                  Required:
                  - ext-json (root)

                Contents: 44 files (146.34KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];

        yield 'PHAR with requirement checker; one PHP requirement' => [
            ['phar' => self::FIXTURES.'/req-checker-php.phar'],
            <<<'OUTPUT'

                API Version: 1.1.0

                Built with Box: dev-main@b2c33cd

                Archive Compression: None
                Files Compression: None

                Signature: SHA-1
                Signature Hash: 6A431D8295B875434CA97538366DE7E022BCF56F

                Metadata: None

                Timestamp: 1697989484 (2023-10-22T15:44:44+00:00)

                RequirementChecker:
                  Required:
                  - PHP ^7.2 (root)

                Contents: 45 files (147.25KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];

        yield 'PHAR with requirement checker; one conflict requirement' => [
            ['phar' => self::FIXTURES.'/req-checker-conflict.phar'],
            <<<'OUTPUT'

                API Version: 1.1.0

                Built with Box: dev-main@b2c33cd

                Archive Compression: None
                Files Compression: None

                Signature: SHA-1
                Signature Hash: 6DCD58032AFFB47AA4DBE0B1CD96417E9CEFDF13

                Metadata: None

                Timestamp: 1697989373 (2023-10-22T15:42:53+00:00)

                RequirementChecker:
                  Conflict:
                  - ext-aerospike (root)

                Contents: 44 files (146.65KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];

        yield 'PHAR with requirement checker; one PHP and extension requirement' => [
            ['phar' => self::FIXTURES.'/req-checker-ext-and-php.phar'],
            <<<'OUTPUT'

                API Version: 1.1.0

                Built with Box: dev-main@b2c33cd

                Archive Compression: None
                Files Compression: None

                Signature: SHA-1
                Signature Hash: C02F6BDF75EED4D5297ECB176E44EB202E29CF16

                Metadata: None

                Timestamp: 1697989464 (2023-10-22T15:44:24+00:00)

                RequirementChecker:
                  Required:
                  - PHP ^7.2 (root)
                  - ext-json (root)

                Contents: 45 files (147.59KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];

        yield 'PHAR with requirement checker; one PHP and extension and conflict requirement' => [
            ['phar' => self::FIXTURES.'/req-checker-ext-and-php-and-conflict.phar'],
            <<<'OUTPUT'

                API Version: 1.1.0

                Built with Box: dev-main@b2c33cd

                Archive Compression: None
                Files Compression: None

                Signature: SHA-1
                Signature Hash: 2882E27FCEE2268DB6E18A7BBB8B92906F286458

                Metadata: None

                Timestamp: 1697989559 (2023-10-22T15:45:59+00:00)

                RequirementChecker:
                  Required:
                  - PHP ^7.2 (root)
                  - ext-json (root)
                  Conflict:
                  - ext-aerospike (root)

                Contents: 45 files (148.23KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];
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
