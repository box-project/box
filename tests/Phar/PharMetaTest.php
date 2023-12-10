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

namespace KevinGH\Box\Phar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use function rtrim;
use function Safe\file_get_contents;

/**
 * @internal
 */
#[CoversClass(PharMeta::class)]
#[RunTestsInSeparateProcesses]
final class PharMetaTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/extract';

    #[DataProvider('pharMetaProvider')]
    public function test_it_can_be_serialized_and_deserialized(PharMeta $pharMeta): void
    {
        $newPharMeta = PharMeta::fromJson($pharMeta->toJson());

        self::assertEquals($pharMeta, $newPharMeta);
    }

    public static function pharMetaProvider(): iterable
    {
        yield 'minimal' => [
            new PharMeta(
                CompressionAlgorithm::NONE,
                null,
                null,
                null,
                null,
                1_509_920_675,
                null,
                [],
            ),
        ];

        yield 'nominal' => [
            new PharMeta(
                CompressionAlgorithm::NONE,
                ['hash' => 'B4CA...', 'hash_type' => 'SHA-512'],
                '<?php __HALT_COMPILER(); ?>',
                '1.1.0',
                <<<'EOL'
                    (object) array(
                       'action' => 'sayHello',
                    )
                    EOL,
                1_509_920_675,
                <<<'EOF'
                    -----BEGIN PUBLIC KEY-----
                    MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAKuZkrHT54KtuBCTrR36+4tibd+2un9b
                    aLFs3X+RHc/jDCXL8pJATz049ckfcfd2ZCMIzH1PHew8H+EMhy4CbSECAwEAAQ==
                    -----END PUBLIC KEY-----

                    EOF,
                [
                    'sample.php' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 29,
                    ],
                ],
            ),
        ];
    }

    #[DataProvider('pharProvider')]
    public function test_it_can_be_created_from_phars(
        string $file,
        ?string $pubKey,
        PharMeta $expected,
    ): void {
        // We create the PHAR instance _within_ the test method since if it was done in the provider
        // it would pollute the main process, even if executed in a separate process.
        $phar = PharFactory::create($file);

        $actual = PharMeta::fromPhar(
            $phar,
            null === $pubKey ? null : file_get_contents($pubKey),
        );

        self::assertEquals($expected, $actual);
    }

    public static function pharProvider(): iterable
    {
        $pharPath = self::FIXTURES_DIR.'/../phar/simple-phar.phar';

        $defaultStub = self::getStub(self::FIXTURES_DIR.'/../phar/default-phar-stub.php');
        $oldDefaultPharStub = self::getStub(self::FIXTURES_DIR.'/../phar/old-default-phar-stub.php');
        $sha512Stub = self::getStub(self::FIXTURES_DIR.'/sha512-phar-stub.php');

        yield 'simple PHAR' => [
            $pharPath,
            null,
            new PharMeta(
                CompressionAlgorithm::NONE,
                [
                    'hash' => '55AE0CCD6D3A74BE41E19CD070A655A73FEAEF8342084A0801954943FBF219ED',
                    'hash_type' => 'SHA-256',
                ],
                $defaultStub,
                '1.1.0',
                null,
                1_680_285_013,
                null,
                [
                    'sample.php' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 36,
                    ],
                ],
            ),
        ];

        yield 'simple PHAR (from 2017)' => [
            self::FIXTURES_DIR.'/../phar/simple-phar-2017.phar',
            null,
            new PharMeta(
                CompressionAlgorithm::NONE,
                [
                    'hash' => '191723EE056C62E3179FDE1B792AA03040FCEF92',
                    'hash_type' => 'SHA-1',
                ],
                $oldDefaultPharStub,
                '1.1.0',
                null,
                1_509_920_675,
                null,
                [
                    'foo.php' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 29,
                    ],
                ],
            ),
        ];

        yield 'simple PHAR with a directory' => [
            self::FIXTURES_DIR.'/../phar/simple-phar-with-dir.phar',
            null,
            new PharMeta(
                CompressionAlgorithm::NONE,
                [
                    'hash' => '60F54D57678A86930BD72356401CD396ECD7409CC35C0C7B99BF510CB73CD0D9',
                    'hash_type' => 'SHA-256',
                ],
                $defaultStub,
                '1.1.0',
                null,
                1_680_680_933,
                null,
                [
                    'sample.php' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 36,
                    ],
                    'dir0/foo' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                ],
            ),
        ];

        if (extension_loaded('zlib')) {
            yield 'GZ compressed simple PHAR' => [
                self::FIXTURES_DIR.'/../phar/simple-phar.phar.gz',
                null,
                new PharMeta(
                    CompressionAlgorithm::GZ,
                    [
                        'hash' => 'F138B1416BE77302A71534DAE47B08E484448CF457FECA9B39B2E82BE459137D',
                        'hash_type' => 'SHA-256',
                    ],
                    $defaultStub,
                    '1.1.0',
                    null,
                    1_680_469_485,
                    null,
                    [
                        'sample.php' => [
                            'compression' => CompressionAlgorithm::NONE,
                            'compressedSize' => 36,
                        ],
                    ],
                ),
            ];
        }

        if (extension_loaded('bz2')) {
            yield 'BZ2 compressed simple PHAR' => [
                self::FIXTURES_DIR.'/../phar/simple-phar.phar.bz2',
                null,
                new PharMeta(
                    CompressionAlgorithm::BZ2,
                    [
                        'hash' => 'FD908A5DC60C593EAEED9B17567FB9A48F2C74B2F7EFF35FAA64C9D708C19478',
                        'hash_type' => 'SHA-256',
                    ],
                    $defaultStub,
                    '1.1.0',
                    null,
                    1_680_469_504,
                    null,
                    [
                        'sample.php' => [
                            'compression' => CompressionAlgorithm::NONE,
                            'compressedSize' => 36,
                        ],
                    ],
                ),
            ];
        }

        yield 'sha512 signed PHAR' => [
            self::FIXTURES_DIR.'/sha512.phar',
            null,
            new PharMeta(
                CompressionAlgorithm::NONE,
                [
                    'hash' => 'B4CAE177138A773283A748C8770A7142F0CC36D6EE88E37900BCF09A92D840D237CE3F3B47C2C7B39AC2D2C0F9A16D63FE70E1A455723DD36840B6E2E64E2130',
                    'hash_type' => 'SHA-512',
                ],
                $sha512Stub,
                '1.1.0',
                null,
                1_374_531_272,
                null,
                [
                    'index.php' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 30,
                    ],
                ],
            ),
        ];

        yield 'OpenSSL signed PHAR' => [
            self::FIXTURES_DIR.'/openssl.phar',
            self::FIXTURES_DIR.'/openssl.phar.pubkey',
            new PharMeta(
                CompressionAlgorithm::NONE,
                [
                    'hash' => '54AF1D4E5459D3A77B692E46FDB9C965D1C7579BD1F2AD2BECF4973677575444FE21E104B7655BA3D088090C28DF63D14876B277C423C8BFBCDB9E3E63F9D61A',
                    'hash_type' => 'OpenSSL',
                ],
                $sha512Stub,
                '1.1.0',
                null,
                1_374_531_313,
                <<<'EOF'
                    -----BEGIN PUBLIC KEY-----
                    MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAKuZkrHT54KtuBCTrR36+4tibd+2un9b
                    aLFs3X+RHc/jDCXL8pJATz049ckfcfd2ZCMIzH1PHew8H+EMhy4CbSECAwEAAQ==
                    -----END PUBLIC KEY-----

                    EOF,
                [
                    'index.php' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 30,
                    ],
                ],
            ),
        ];

        yield 'PHAR with a string value as metadata' => [
            self::FIXTURES_DIR.'/../phar/metadata/string-metadata.phar',
            null,
            new PharMeta(
                CompressionAlgorithm::NONE,
                [
                    'hash' => 'A9D407999E197A1159F12BE0F4362249625D456E9E7362C8CBA0ECABE8B3C601',
                    'hash_type' => 'SHA-256',
                ],
                $defaultStub,
                '1.1.0',
                "'Hello world!'",
                1_680_366_918,
                null,
                [
                    'sample.php' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 36,
                    ],
                ],
            ),
        ];

        yield 'PHAR with a float value as metadata' => [
            self::FIXTURES_DIR.'/../phar/metadata/float-metadata.phar',
            null,
            new PharMeta(
                CompressionAlgorithm::NONE,
                [
                    'hash' => '7A504BE5DB7793106265A03357C5DB55DFBA51265464F1F56CCD8E2B51CA046A',
                    'hash_type' => 'SHA-256',
                ],
                $defaultStub,
                '1.1.0',
                '-19.8',
                1_680_366_947,
                null,
                [
                    'sample.php' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 36,
                    ],
                ],
            ),
        ];

        yield 'PHAR with an stdClass value as metadata' => [
            self::FIXTURES_DIR.'/../phar/metadata/stdClass-metadata.phar',
            null,
            new PharMeta(
                CompressionAlgorithm::NONE,
                [
                    'hash' => 'EE93788AAE2DE0098532021A425A343595F1066D9638B074E9AEA6BC6CA08D22',
                    'hash_type' => 'SHA-256',
                ],
                $defaultStub,
                '1.1.0',
                <<<'EOL'
                    (object) array(
                       'action' => 'sayHello',
                    )
                    EOL,
                1_680_367_053,
                null,
                [
                    'sample.php' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 36,
                    ],
                ],
            ),
        ];

        yield 'simple tar' => [
            self::FIXTURES_DIR.'/../phar/simple.tar',
            null,
            new PharMeta(
                CompressionAlgorithm::NONE,
                null,
                null,
                null,
                null,
                1_680_284_754,
                null,
                [
                    'sample.txt' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 13,
                    ],
                ],
            ),
        ];

        if (extension_loaded('zlib')) {
            yield 'GZ compressed simple tar' => [
                self::FIXTURES_DIR.'/../phar/simple.tar.gz',
                null,
                new PharMeta(
                    CompressionAlgorithm::GZ,
                    null,
                    null,
                    null,
                    null,
                    1_680_284_663,
                    null,
                    [
                        'sample.txt' => [
                            'compression' => CompressionAlgorithm::NONE,
                            'compressedSize' => 13,
                        ],
                    ],
                ),
            ];
        }

        if (extension_loaded('zlib')) {
            yield 'ZIP compressed simple tar' => [
                self::FIXTURES_DIR.'/../phar/simple.zip',
                null,
                new PharMeta(
                    CompressionAlgorithm::NONE,
                    null,
                    null,
                    null,
                    null,
                    1_680_284_660,
                    null,
                    [
                        'sample.txt' => [
                            'compression' => CompressionAlgorithm::GZ,
                            'compressedSize' => 15,
                        ],
                    ],
                ),
            ];
        }

        if (extension_loaded('bz2')) {
            yield 'BZ2 compressed simple tar' => [
                self::FIXTURES_DIR.'/../phar/simple.tar.bz2',
                null,
                new PharMeta(
                    CompressionAlgorithm::BZ2,
                    null,
                    null,
                    null,
                    null,
                    1_680_284_663,
                    null,
                    [
                        'sample.txt' => [
                            'compression' => CompressionAlgorithm::NONE,
                            'compressedSize' => 13,
                        ],
                    ],
                ),
            ];
        }

        yield 'file keys are sorted' => [
            self::FIXTURES_DIR.'/../info/complete-tree.phar',
            null,
            new PharMeta(
                CompressionAlgorithm::NONE,
                [
                    'hash' => '5FE61595A3D773538C3CE6006FBC3679272F6DF118B3229AFD606462B772C414',
                    'hash_type' => 'SHA-256',
                ],
                $defaultStub,
                '1.1.0',
                null,
                1_680_645_848,
                null,
                [
                    '.hidden-dir/.hidden-file' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    '.hidden-dir/dir/fileZ' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    '.hidden-dir/fileY' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    '.hidden-file' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    'dir0/dir01/fileC' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    'dir0/dir01/fileD' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    'dir0/dir02/dir020/fileE' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    'dir0/dir02/dir020/fileF' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    'dir0/fileA' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    'dir0/fileB' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    'dir1/fileG' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    'dir1/fileH' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                    'fileX' => [
                        'compression' => CompressionAlgorithm::NONE,
                        'compressedSize' => 0,
                    ],
                ],
            ),
        ];
    }

    private static function getStub(string $path): string
    {
        // We trim the last line returns since phpStorm may interfere with the copied file appending it on save.
        return rtrim(
            file_get_contents($path),
            "\n",
        );
    }
}
