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

use Phar;
use PHPUnit\Framework\TestCase;
use function rtrim;
use function Safe\file_get_contents;

/**
 * @covers \KevinGH\Box\Phar\PharMeta
 *
 * @internal
 */
final class PharMetaTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/extract';

    /**
     * @dataProvider pharMetaProvider
     */
    public function test_it_can_be_serialized_and_deserialized(PharMeta $pharMeta): void
    {
        $newPharMeta = PharMeta::fromJson($pharMeta->toJson());

        self::assertEquals($pharMeta, $newPharMeta);
    }

    public static function pharMetaProvider(): iterable
    {
        yield 'minimal' => [
            new PharMeta(
                null,
                null,
                null,
                null,
                null,
            ),
        ];

        yield 'nominal' => [
            new PharMeta(
                ['hash' => 'B4CA...', 'hash_type' => 'SHA-512'],
                '<?php __HALT_COMPILER(); ?>',
                '1.1.0',
                <<<'EOL'
                    (object) array(
                       'action' => 'sayHello',
                    )
                    EOL,
                <<<'EOF'
                    -----BEGIN PUBLIC KEY-----
                    MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAKuZkrHT54KtuBCTrR36+4tibd+2un9b
                    aLFs3X+RHc/jDCXL8pJATz049ckfcfd2ZCMIzH1PHew8H+EMhy4CbSECAwEAAQ==
                    -----END PUBLIC KEY-----

                    EOF,
            ),
        ];
    }

    /**
     * @runInSeparateProcess
     * @dataProvider pharProvider
     */
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
        $oldDefaultPharStub = self::getStub(self::FIXTURES_DIR.'/old-default-phar-stub.php');
        $sha512Stub = self::getStub(self::FIXTURES_DIR.'/sha512-phar-stub.php');

        yield 'simple PHAR' => [
            $pharPath,
            null,
            new PharMeta(
                [
                    'hash' => '55AE0CCD6D3A74BE41E19CD070A655A73FEAEF8342084A0801954943FBF219ED',
                    'hash_type' => 'SHA-256',
                ],
                $defaultStub,
                '1.1.0',
                null,
                null,
            ),
        ];

        if (extension_loaded('zlib')) {
            yield 'GZ compressed simple PHAR' => [
                self::FIXTURES_DIR.'/gz-compressed-phar.phar',
                null,
                new PharMeta(
                    [
                        'hash' => '3CCDA01B80C1CAC91494EA59BBAFA479E38CD120',
                        'hash_type' => 'SHA-1',
                    ],
                    $defaultStub,
                    '1.1.0',
                    null,
                    null,
                ),
            ];
        }

        yield 'sha512 signed PHAR' => [
            self::FIXTURES_DIR.'/sha512.phar',
            null,
            new PharMeta(
                [
                    'hash' => 'B4CAE177138A773283A748C8770A7142F0CC36D6EE88E37900BCF09A92D840D237CE3F3B47C2C7B39AC2D2C0F9A16D63FE70E1A455723DD36840B6E2E64E2130',
                    'hash_type' => 'SHA-512',
                ],
                $sha512Stub,
                '1.1.0',
                null,
                null,
            ),
        ];

        yield 'OpenSSL signed PHAR' => [
            self::FIXTURES_DIR.'/openssl.phar',
            self::FIXTURES_DIR.'/openssl.phar.pubkey',
            new PharMeta(
                [
                    'hash' => '54AF1D4E5459D3A77B692E46FDB9C965D1C7579BD1F2AD2BECF4973677575444FE21E104B7655BA3D088090C28DF63D14876B277C423C8BFBCDB9E3E63F9D61A',
                    'hash_type' => 'OpenSSL',
                ],
                $sha512Stub,
                '1.1.0',
                null,
                <<<'EOF'
                    -----BEGIN PUBLIC KEY-----
                    MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAKuZkrHT54KtuBCTrR36+4tibd+2un9b
                    aLFs3X+RHc/jDCXL8pJATz049ckfcfd2ZCMIzH1PHew8H+EMhy4CbSECAwEAAQ==
                    -----END PUBLIC KEY-----

                    EOF,
            ),
        ];

        yield 'PHAR with a string value as metadata' => [
            self::FIXTURES_DIR.'/../phar/metadata/string-metadata.phar',
            null,
            new PharMeta(
                [
                    'hash' => 'A9D407999E197A1159F12BE0F4362249625D456E9E7362C8CBA0ECABE8B3C601',
                    'hash_type' => 'SHA-256',
                ],
                $defaultStub,
                '1.1.0',
                "'Hello world!'",
                null,
            ),
        ];

        yield 'PHAR with a float value as metadata' => [
            self::FIXTURES_DIR.'/../phar/metadata/float-metadata.phar',
            null,
            new PharMeta(
                [
                    'hash' => '7A504BE5DB7793106265A03357C5DB55DFBA51265464F1F56CCD8E2B51CA046A',
                    'hash_type' => 'SHA-256',
                ],
                $defaultStub,
                '1.1.0',
                '-19.8',
                null,
            ),
        ];

        yield 'PHAR with an stdClass value as metadata' => [
            self::FIXTURES_DIR.'/../phar/metadata/stdClass-metadata.phar',
            null,
            new PharMeta(
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
                null,
            ),
        ];

        yield 'simple tar' => [
            self::FIXTURES_DIR.'/../phar/simple.tar',
            null,
            new PharMeta(
                null,
                null,
                null,
                null,
                null,
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
