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
use KevinGH\Box\Pharaoh\InvalidPhar;
use KevinGH\Box\Test\CommandTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use function count;
use function KevinGH\Box\FileSystem\make_path_relative;

/**
 * @covers \KevinGH\Box\Console\Command\Extract
 *
 * @runTestsInSeparateProcesses This is necessary as instantiating a PHAR in memory may load/autoload some stuff which
 *                              can create undesirable side-effects.
 *
 * @internal
 */
class ExtractTest extends CommandTestCase
{
    private const FIXTURES = __DIR__.'/../../../fixtures/extract';

    protected function getCommand(): Command
    {
        return new Extract();
    }

    /**
     * @dataProvider pharProvider
     */
    public function test_it_can_extract_a_phar(
        string $pharPath,
        array $expectedFiles,
    ): void {
        $this->commandTester->setInputs(['yes']);
        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => $pharPath,
                'output' => $this->tmp,
            ],
            ['interactive' => false],
        );

        $actualFiles = $this->collectExtractedFiles();

        $this->assertSameOutput('', ExitCode::SUCCESS);
        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);
    }

    private static function pharProvider(): iterable
    {
        $pharPath = self::FIXTURES.'/simple-phar.phar';

        $expectedSimplePharFiles = [
            '.hidden' => 'baz',
            'foo' => 'bar',
            '.phar/stub.php' => file_get_contents(self::FIXTURES.'/simple-phar-stub.php'),
            '.phar/signature.json' => '{"hash":"966C5D96F7A3C67F8FC06D3DF55CE4C9AC820F47","hash_type":"SHA-1"}',
        ];

        yield 'simple PHAR' => [
            $pharPath,
            $expectedSimplePharFiles,
        ];

        yield 'simple PHAR without the PHAR extension' => [
            self::FIXTURES.'/simple-phar',
            $expectedSimplePharFiles,
        ];

        if (extension_loaded('zlib')) {
            yield 'GZ compressed simple PHAR' => [
                self::FIXTURES.'/gz-compressed-phar.phar',
                array_merge(
                    $expectedSimplePharFiles,
                    [
                        '.phar/signature.json' => '{"hash":"3CCDA01B80C1CAC91494EA59BBAFA479E38CD120","hash_type":"SHA-1"}',
                    ],
                ),
            ];
        }

        yield 'sha512 signed PHAR' => [
            self::FIXTURES.'/sha512.phar',
            [
                'index.php' => <<<'PHP'
                    <?php echo "Hello, world!\n";

                    PHP,
                '.phar/stub.php' => file_get_contents(self::FIXTURES.'/sha512-phar-stub.php'),
                '.phar/signature.json' => '{"hash":"B4CAE177138A773283A748C8770A7142F0CC36D6EE88E37900BCF09A92D840D237CE3F3B47C2C7B39AC2D2C0F9A16D63FE70E1A455723DD36840B6E2E64E2130","hash_type":"SHA-512"}',
            ],
        ];

        yield 'OpenSSL signed PHAR' => [
            self::FIXTURES.'/openssl.phar',
            [
                'index.php' => <<<'PHP'
                    <?php echo "Hello, world!\n";

                    PHP,
                '.phar/pubkey' => <<<'EOF'
                    -----BEGIN PUBLIC KEY-----
                    MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAKuZkrHT54KtuBCTrR36+4tibd+2un9b
                    aLFs3X+RHc/jDCXL8pJATz049ckfcfd2ZCMIzH1PHew8H+EMhy4CbSECAwEAAQ==
                    -----END PUBLIC KEY-----

                    EOF,
                '.phar/stub.php' => file_get_contents(self::FIXTURES.'/sha512-phar-stub.php'),
                '.phar/signature.json' => '{"hash":"54AF1D4E5459D3A77B692E46FDB9C965D1C7579BD1F2AD2BECF4973677575444FE21E104B7655BA3D088090C28DF63D14876B277C423C8BFBCDB9E3E63F9D61A","hash_type":"OpenSSL"}',
            ],
        ];
    }

    /**
     * @dataProvider confirmationQuestionProvider
     */
    public function test_it_asks_confirmation_before_deleting_the_output_dir(
        bool $outputDirExists,
        bool $interactive,
        bool $input,
        bool $expectedToSucceed,
    ): void {
        $outputDir = $outputDirExists ? $this->tmp : $this->tmp.'/subdir';

        $this->commandTester->setInputs([$input ? 'yes' : 'no']);
        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => self::FIXTURES.'/simple-phar.phar',
                'output' => $outputDir,
            ],
            ['interactive' => $interactive],
        );

        $actualFiles = $this->collectExtractedFiles();

        if ($expectedToSucceed) {
            self::assertSame(ExitCode::SUCCESS, $this->commandTester->getStatusCode());
            self::assertGreaterThan(0, count($actualFiles));
        } else {
            self::assertSame(ExitCode::FAILURE, $this->commandTester->getStatusCode());
            self::assertSame(0, count($actualFiles));
        }
    }

    public static function confirmationQuestionProvider(): iterable
    {
        yield 'exists; accept' => [
            true,
            true,
            true,
            true,
        ];

        yield 'exists; refuse' => [
            true,
            true,
            false,
            false,
        ];

        yield 'does not exist: the question is not asked' => [
            false,
            true,
            false,
            true,
        ];

        yield 'exists; not interactive; accept' => [
            true,
            false,
            true,
            true,
        ];

        yield 'exists; not interactive; refuse' => [
            true,
            false,
            false,
            true,
        ];

        yield 'not interactive; does not exist: the question is not asked' => [
            false,
            false,
            false,
            true,
        ];
    }

    /**
     * @dataProvider invalidPharPath
     */
    public function test_it_cannot_extract_an_invalid_phar(
        string $pharPath,
        string $exceptionClassName,
        string $expectedExceptionMessage,
    ): void {
        try {
            $this->commandTester->execute(
                [
                    'command' => 'extract',
                    'phar' => $pharPath,
                    'output' => $this->tmp,
                ],
                ['interactive' => false],
            );

            self::fail('Expected exception to be thrown.');
        } catch (InvalidPhar $exception) {
            // Continue
        }

        self::assertSame(
            $exceptionClassName,
            $exception::class,
        );
        self::assertMatchesRegularExpression(
            $expectedExceptionMessage,
            $exception->getMessage(),
        );

        self::assertSame([], $this->collectExtractedFiles());
    }

    public static function invalidPharPath(): iterable
    {
        yield 'not a valid PHAR with the PHAR extension' => [
            self::FIXTURES.'/invalid.phar',
            InvalidPhar::class,
            '/^Could not create a Phar or PharData instance for the file/',
        ];

        yield 'not a valid PHAR without the PHAR extension' => [
            self::FIXTURES.'/invalid',
            InvalidPhar::class,
            '/^Could not create a Phar or PharData instance for the file .+$/',
        ];

        yield 'corrupted PHAR (was valid; got tempered with)' => [
            self::FIXTURES.'/corrupted.phar',
            InvalidPhar::class,
            '/^Could not create a Phar or PharData instance for the file .+$/',
        ];

        yield 'OpenSSL signed PHAR without a pubkey' => [
            self::FIXTURES.'/openssl-no-pubkey.phar',
            InvalidPhar::class,
            '/^Could not create a Phar or PharData instance for the file .+$/',
        ];

        yield 'OpenSSL signed PHAR with incorrect pubkey' => [
            self::FIXTURES.'/incorrect-key-openssl.phar',
            InvalidPhar::class,
            '/^Could not create a Phar or PharData instance for the file .+$/',
        ];
    }

    /**
     * @return array<string,string>
     */
    private function collectExtractedFiles(): array
    {
        $finder = Finder::create()
            ->files()
            ->in($this->tmp)
            ->ignoreDotFiles(false);

        $files = [];

        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $filePath = make_path_relative($file->getPathname(), $this->tmp);

            $files[$filePath] = $file->getContents();
        }

        return $files;
    }
}
