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
use KevinGH\Box\Platform;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use Phar;
use PharData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function extension_loaded;
use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
#[CoversClass(PharFactory::class)]
#[CoversClass(InvalidPhar::class)]
final class PharFactoryTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/phar';

    use RequiresPharReadonlyOff;

    protected function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        parent::setUp();
    }

    #[DataProvider('validPharProvider')]
    public function test_it_can_create_phar_instance(string $file): void
    {
        $phar = PharFactory::createPhar($file);

        self::assertSame(Phar::class, $phar::class);
    }

    #[DataProvider('validPharDataProvider')]
    public function test_it_can_create_phar_data_instance(string $file): void
    {
        $pharData = PharFactory::createPharData($file);

        self::assertSame(PharData::class, $pharData::class);
    }

    #[DataProvider('validPharAndPharDataProvider')]
    public function test_it_can_create_phar_or_phar_data_instance(string $file): void
    {
        PharFactory::create($file);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidPharProvider')]
    public function test_it_fails_with_a_comprehensive_error_when_cannot_create_a_phar(
        string $file,
        string $expectedExceptionMessageRegex,
    ): void {
        $this->expectException(InvalidPhar::class);
        $this->expectExceptionMessageMatches($expectedExceptionMessageRegex);

        PharFactory::createPhar($file);
    }

    public function test_it_fails_with_a_comprehensive_error_when_cannot_create_a_phar_which_is_a_copy(): void
    {
        $this->expectException(InvalidPhar::class);
        $this->expectExceptionMessageMatches(
            '/^Could not create a Phar instance for the file ".+"\ \(of the original file "original\.phar"\)\. The file must have the extension "\.phar"\.$/',
        );

        PharFactory::createPhar(
            self::FIXTURES_DIR.'/empty-pdf.pdf',
            'original.phar',
        );
    }

    #[DataProvider('invalidPharDataProvider')]
    public function test_it_fails_with_a_comprehensive_error_when_cannot_create_a_phar_data(
        string $file,
        string $expectedExceptionMessageRegex,
    ): void {
        $this->expectException(InvalidPhar::class);
        $this->expectExceptionMessageMatches($expectedExceptionMessageRegex);

        PharFactory::createPharData($file);
    }

    public static function validPharProvider(): iterable
    {
        yield 'simple PHAR' => [self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'simple-phar.phar'];

        // This works but results in an "empty PHAR": no signature, no stub, will fail to be executed, but can be
        // instantiated nonetheless.
        $tarVariants = [
            'simple.zip.phar',
            'simple.tar.phar',
            'simple.tar.gz.phar',
        ];

        if (extension_loaded('bz2')) {
            $tarVariants[] = 'simple.tar.bz2.phar';
        }

        if (Platform::isOSX()) {
            // On Linux the following would fail with "is not a phar archive. Use PharData::__construct() for a standard zip or tar archive"
            foreach ($tarVariants as $tarVariant) {
                yield $tarVariant => [self::FIXTURES_DIR.DIRECTORY_SEPARATOR.$tarVariant];
            }
        }

        $signedPhars = [
            'MD5' => 'simple-phar-md5-sign.phar',
            'SHA1' => 'simple-phar-sha1-sign.phar',
            'SHA256' => 'simple-phar-sha256-sign.phar',
            'SHA512' => 'simple-phar-sha512-sign.phar',
            'OpenSSL (no passphrase)' => 'simple-phar-openssl-sign.phar',
        ];

        foreach ($signedPhars as $alogrithm => $signedPhar) {
            yield $alogrithm => [self::FIXTURES_DIR.DIRECTORY_SEPARATOR.$signedPhar];
        }
    }

    public static function validPharDataProvider(): iterable
    {
        $data = [
            'simple tar archive' => 'simple.tar',
            'simple ZIP archive' => 'simple.zip',
            'simple GZ archive' => 'simple.tar.gz',
        ];

        if (extension_loaded('bz2')) {
            $data['simple BZ2 archive'] = 'simple.tar.bz2';
        }

        foreach ($data as $label => $fileName) {
            yield $label => [self::FIXTURES_DIR.DIRECTORY_SEPARATOR.$fileName];
        }
    }

    public static function validPharAndPharDataProvider(): iterable
    {
        yield from self::validPharProvider();
        yield from self::validPharDataProvider();
    }

    public static function invalidPharProvider(): iterable
    {
        yield 'URL of a valid PHAR' => [
            'https://github.com/box-project/box/releases/download/4.3.8/box.phar',
            '/^Could not create a Phar or PharData instance for the file path ".+"\. PHAR objects can only be created from local files\.$/',
        ];

        yield 'FTPS URL of a valid PHAR' => [
            'ftps://github.com/box-project/box/releases/download/4.3.8/box.phar',
            '/^Could not create a Phar or PharData instance for the file path ".+"\. PHAR objects can only be created from local files\.$/',
        ];

        yield 'local stream' => [
            'php://stdout',
            '/^Could not create a Phar or PharData instance for the file path ".+"\. PHAR objects can only be created from local files\.$/',
        ];

        yield 'non existent file with a valid PHAR name' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'non-existent-file.phar',
            '/^Could not find the file ".+"\.$/',
        ];

        yield 'non-compressed binary file (empty PDF)' => [
            self::FIXTURES_DIR.'/empty-pdf.pdf',
            '/^Could not create a Phar instance for the file ".+"\. The file must have the extension "\.phar"\.$/',
        ];

        yield 'non-compressed empty file' => [
            self::FIXTURES_DIR.'/empty-file.phar',
            '/^Could not create a Phar instance for the file ".+"\. The archive is corrupted: Truncated entry\.$/',
        ];

        $validPharDatasWithoutPharExtension = [
            'simple.zip',
            'simple.tar',
            'simple.tar.gz',
        ];

        if (extension_loaded('bz2')) {
            $validPharDatasWithoutPharExtension[] = 'simple.tar.bz2';
        }

        foreach ($validPharDatasWithoutPharExtension as $validPharDataWithoutPharExtension) {
            yield $validPharDataWithoutPharExtension => [
                self::FIXTURES_DIR.DIRECTORY_SEPARATOR.$validPharDataWithoutPharExtension,
                '/^Could not create a Phar instance for the file ".+"\. The file must have the extension "\.phar"\.$/',
            ];
        }

        yield 'PHAR without the __HALT_COMPILER(); ?> token' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'corruted-phar-no-halt-compiler.phar',
            '/^Could not create a Phar instance for the file ".+"\. The archive is corrupted: __HALT_COMPILER\(\); not found\.$/',
        ];

        yield 'OpenSSL signed PHAR without its pubkey' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'simple-phar-openssl-sign-without-pubkey.phar',
            '/^Could not create a Phar instance for the file ".+"\. The OpenSSL signature could not be read or verified\.$/',
        ];

        yield 'OpenSSL signed PHAR without a different pubkey' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'simple-phar-openssl-sign-with-diff-pubkey.phar',
            '/^Could not create a Phar instance for the file ".+"\. The OpenSSL signature could not be read or verified\.$/',
        ];

        yield 'OpenSSL signed PHAR without an invalid pubkey' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'simple-phar-openssl-sign-with-invalid-pubkey.phar',
            '/^Could not create a Phar instance for the file ".+"\. The OpenSSL signature could not be read or verified\.$/',
        ];

        yield 'PHAR for which the stub content has been altered without updating its signature' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'corrupted-phar-altered-stub.phar',
            '/^Could not create a Phar instance for the file ".+"\. The archive signature is broken\.$/',
        ];

        yield 'PHAR for which the content has been altered without updating its signature' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'corrupted-phar-altered-binary.phar',
            '/^Could not create a Phar instance for the file ".+"\. The archive signature is broken\.$/',
        ];

        yield 'PHAR for which an included file has been altered without updating its signature' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'corrupted-phar-altered-included-file.phar',
            '/^Could not create a Phar instance for the file ".+"\. The archive signature is broken\.$/',
        ];
    }

    public static function invalidPharDataProvider(): iterable
    {
        // valid PHARs cannot be PharData instances
        foreach (self::validPharProvider() as $label => [$file]) {
            yield 'valid PHAR; '.$label => [
                $file,
                '/^Could not create a PharData instance for the file ".+"\. The file must have the extension "\.zip", "\.tar", "\.tar\.bz2" or "\.tar\.gz"\.$/',
            ];
        }

        yield 'URL of a valid tar' => [
            'https://github.com/box-project/box/releases/download/4.3.8/box.tar',
            '/^Could not create a Phar or PharData instance for the file path ".+"\. PHAR objects can only be created from local files\.$/',
        ];

        yield 'FTPS URL of a valid PHAR' => [
            'ftps://github.com/box-project/box/releases/download/4.3.8/box.tar',
            '/^Could not create a Phar or PharData instance for the file path ".+"\. PHAR objects can only be created from local files\.$/',
        ];

        yield 'local stream' => [
            'php://stdout',
            '/^Could not create a Phar or PharData instance for the file path ".+"\. PHAR objects can only be created from local files\.$/',
        ];

        yield 'non existent file with a valid PharData name' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'non-existent-file.tar',
            '/^Could not find the file ".+"\.$/',
        ];

        yield 'corrupted ZIP file' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'corrupted-simple.zip',
            '/^Could not create a PharData instance for the file ".+"\. The archive is corrupted: __HALT_COMPILER\(\); not found\.$/',
        ];

        yield 'non-compressed binary file (empty PDF)' => [
            self::FIXTURES_DIR.'/empty-pdf.pdf',
            '/^Could not create a PharData instance for the file ".+"\. The archive is corrupted: __HALT_COMPILER\(\); not found\.$/',
        ];

        yield 'non-compressed empty file' => [
            self::FIXTURES_DIR.'/empty-file.zip',
            '/^Could not create a PharData instance for the file ".+"\. The archive is corrupted: Truncated entry\.$/',
        ];

        yield 'OpenSSL signed PHAR renamed to tar without its pubkey' => [
            self::FIXTURES_DIR.'/simple-phar-openssl-sign.tar',
            '/^Could not create a PharData instance for the file ".+"\. The OpenSSL signature could not be read or verified\.$/',
        ];

        yield 'signed PHAR renamed to tar for which the stub content has been altered without updating its signature' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'corrupted-phar-altered-stub.tar',
            '/^Could not create a PharData instance for the file ".+"\. The archive signature is broken\.$/',
        ];

        yield 'signed PHAR renamed to tar for which the binary content has been altered without updating its signature' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'corrupted-phar-altered-binary.tar',
            '/^Could not create a PharData instance for the file ".+"\. The archive signature is broken\.$/',
        ];

        yield 'signed PHAR renamed to tar for which an included file content has been altered without updating its signature' => [
            self::FIXTURES_DIR.DIRECTORY_SEPARATOR.'corrupted-phar-altered-included-file.tar',
            '/^Could not create a PharData instance for the file ".+"\. The archive signature is broken\.$/',
        ];
    }
}
