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

use KevinGH\Box\Phar\Throwable\InvalidPhar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function array_keys;
use function basename;
use function rtrim;
use function Safe\file_get_contents;
use function Safe\realpath;

/**
 * @internal
 */
#[CoversClass(PharInfo::class)]
final class PharInfoTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures';

    #[DataProvider('fileProvider')]
    public function test_it_can_be_instantiated(
        string $file,
        ?string $expectedVersion,
        ?array $expectedSignature,
        ?string $expectedPubkey,
        ?string $expectedMetadata,
        ?string $expectedStub,
        array $expectedFileRelativePaths,
    ): void {
        $pharInfo = new PharInfo($file);

        self::assertSame(realpath($file), $pharInfo->getFile());
        self::assertSame(basename($file), $pharInfo->getFileName());
        self::assertSame($expectedVersion, $pharInfo->getVersion());
        self::assertSame($expectedSignature, $pharInfo->getSignature());
        self::assertSame($expectedPubkey, $pharInfo->getPubKeyContent());
        self::assertSame($expectedMetadata, $pharInfo->getNormalizedMetadata());
        self::assertSame($expectedStub, $pharInfo->getStubContent());
        self::assertEqualsCanonicalizing(
            $expectedFileRelativePaths,
            array_keys($pharInfo->getFiles()),
        );

        foreach ($expectedFileRelativePaths as $relativePath) {
            $fileMeta = $pharInfo->getFileMeta($relativePath);

            self::assertSame(['compression', 'compressedSize'], array_keys($fileMeta));
            self::assertInstanceOf(CompressionAlgorithm::class, $fileMeta['compression']);
            self::assertGreaterThanOrEqual(0, $fileMeta['compressedSize']);
        }
    }

    public static function fileProvider(): iterable
    {
        $defaultStub = self::getStub(self::FIXTURES_DIR.'/phar/default-phar-stub.php');
        $oldDefaultStub = self::getStub(self::FIXTURES_DIR.'/phar/old-default-phar-stub.php');

        yield 'simple PHAR (2017)' => [
            self::FIXTURES_DIR.'/phar/simple-phar-2017.phar',
            '1.1.0',
            [
                'hash' => '191723EE056C62E3179FDE1B792AA03040FCEF92',
                'hash_type' => 'SHA-1',
            ],
            null,
            null,
            $oldDefaultStub,
            ['foo.php'],
        ];

        yield 'simple PHAR (PHP 8.1)' => [
            self::FIXTURES_DIR.'/phar/simple-phar.phar',
            '1.1.0',
            [
                'hash' => '55AE0CCD6D3A74BE41E19CD070A655A73FEAEF8342084A0801954943FBF219ED',
                'hash_type' => 'SHA-256',
            ],
            null,
            null,
            $defaultStub,
            ['sample.php'],
        ];

        yield 'OpenSSL signed PHAR' => [
            self::FIXTURES_DIR.'/phar/simple-phar-openssl-sign.phar',
            '1.1.0',
            [
                'hash' => '259C433C3822FD3FA43AEE847405B358B8688B2930A3B438C71144FDBDAF10422DFBDB8A83DABA4819E04AE2EA69407F9370AC972FC1BBB91128B5DB68D5C372904E5B4BFF5C21ABF75AA38EAEB4C4BF757EB25C9A57D02535478EAC9F7D4C99878BC9BD7E6574E48847F47932DE242E0ADFAFDB66B711A00558504DD33D5AD50CD2D34690E2895360408344EF0FEF6E7A2464699A876E4C18F0CF944F3C5784FFF4C87CC602E95A6248BEBF1D9090D1C6042D4FA2E7E4C039DEAD628E52D71DD1E91400EF42E7B995A60C59CAD2B95EC7FA1D84A1D288E5032DB768D00909FEE8256732D91C1292E2F90FE9DF08E58206155550B7834ACCF3A0B7E0A9392C30',
                'hash_type' => 'OpenSSL',
            ],
            file_get_contents(self::FIXTURES_DIR.'/phar/simple-phar-openssl-sign.phar.pubkey'),
            null,
            $defaultStub,
            ['sample.php'],
        ];

        yield 'PHAR with a string value as metadata' => [
            self::FIXTURES_DIR.'/phar/metadata/string-metadata.phar',
            '1.1.0',
            [
                'hash' => 'A9D407999E197A1159F12BE0F4362249625D456E9E7362C8CBA0ECABE8B3C601',
                'hash_type' => 'SHA-256',
            ],
            null,
            "'Hello world!'",
            $defaultStub,
            ['sample.php'],
        ];

        yield 'PHAR with a float value as metadata' => [
            self::FIXTURES_DIR.'/phar/metadata/float-metadata.phar',
            '1.1.0',
            [
                'hash' => '7A504BE5DB7793106265A03357C5DB55DFBA51265464F1F56CCD8E2B51CA046A',
                'hash_type' => 'SHA-256',
            ],
            null,
            '-19.8',
            $defaultStub,
            ['sample.php'],
        ];

        yield 'PHAR with an stdClass value as metadata' => [
            self::FIXTURES_DIR.'/phar/metadata/stdClass-metadata.phar',
            '1.1.0',
            [
                'hash' => 'EE93788AAE2DE0098532021A425A343595F1066D9638B074E9AEA6BC6CA08D22',
                'hash_type' => 'SHA-256',
            ],
            null,
            <<<'EOL'
                (object) array(
                   'action' => 'sayHello',
                )
                EOL,
            $defaultStub,
            ['sample.php'],
        ];

        yield 'simple tar' => [
            self::FIXTURES_DIR.'/phar/simple.tar',
            'No information found',
            null,
            null,
            null,
            null,
            ['sample.txt'],
        ];
    }

    public function test_it_cleans_itself_up_upon_destruction(): void
    {
        $pharInfo = new PharInfo(self::FIXTURES_DIR.'/phar/simple-phar.phar');

        $tmp = $pharInfo->getTmp();

        self::assertDirectoryExists($tmp);

        unset($pharInfo);

        self::assertDirectoryDoesNotExist($tmp);
    }

    public function test_it_can_create_two_instances_of_the_same_phar(): void
    {
        $file = self::FIXTURES_DIR.'/phar/simple-phar.phar';

        new PharInfo($file);
        new PharInfo($file);

        $this->addToAssertionCount(1);
    }

    public function test_it_throws_an_error_when_a_phar_cannot_be_created_due_to_unverifiable_signature(): void
    {
        $file = self::FIXTURES_DIR.'/phar/simple-phar-openssl-sign-with-invalid-pubkey.phar';

        $this->expectException(InvalidPhar::class);
        $this->expectExceptionMessageMatches('/^Could not create a Phar or PharData instance for the file /');

        new PharInfo($file);
    }

    #[DataProvider('equalProvider')]
    public function test_it_can_test_if_two_phars_are_equal(
        string $fileA,
        string $fileB,
        bool $expected,
    ): void {
        $pharInfoA = new PharInfo(realpath($fileA));
        $pharInfoB = new PharInfo(realpath($fileB));

        $actual = $pharInfoA->equals($pharInfoB);

        self::assertSame($expected, $actual);
    }

    public static function equalProvider(): iterable
    {
        yield 'identical PHARs' => [
            self::FIXTURES_DIR.'/phar/simple-phar.phar',
            self::FIXTURES_DIR.'/phar/simple-phar.phar',
            true,
        ];

        yield 'identical PHARs with different compression' => [
            self::FIXTURES_DIR.'/phar/simple-phar.phar',
            self::FIXTURES_DIR.'/phar/simple-phar.phar.gz',
            false,
        ];

        yield 'different files' => [
            self::FIXTURES_DIR.'/diff/simple-phar-foo.phar',
            self::FIXTURES_DIR.'/diff/simple-phar-bar.phar',
            false,
        ];

        yield 'same files with different content' => [
            self::FIXTURES_DIR.'/diff/simple-phar-bar.phar',
            self::FIXTURES_DIR.'/diff/simple-phar-baz.phar',
            false,
        ];

        // TODO: missing cases:
        //   - same PHARs and diff. metadata
        //   - same PHARs and same total compression file count but not the same files compression
        //     (to assert the compression comparison is justified instead of using the existing ::getFilesCompressionCount()).
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
