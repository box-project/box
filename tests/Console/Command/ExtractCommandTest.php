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
use KevinGH\Box\Phar\CompressionAlgorithm;
use KevinGH\Box\Phar\PharMeta;
use KevinGH\Box\Phar\Throwable\InvalidPhar;
use KevinGH\Box\Test\CommandTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use function count;
use function rtrim;
use function Safe\file_get_contents;

/**
 * @internal
 */
#[CoversClass(ExtractCommand::class)]
#[RunTestsInSeparateProcesses]
class ExtractCommandTest extends CommandTestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/extract';

    protected function getCommand(): Command
    {
        return new ExtractCommand();
    }

    #[DataProvider('pharProvider')]
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
                '--internal' => null,
            ],
            ['interactive' => false],
        );

        $actualFiles = $this->collectExtractedFiles();

        $this->assertSameOutput('', ExitCode::SUCCESS);
        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);
    }

    public static function pharProvider(): iterable
    {
        $pharPath = self::FIXTURES_DIR.'/simple-phar.phar';

        $oldDefaultPharStub = self::getStub(self::FIXTURES_DIR.'/simple-phar-stub.php');
        $sha512Stub = self::getStub(self::FIXTURES_DIR.'/sha512-phar-stub.php');

        $pharMeta = new PharMeta(
            CompressionAlgorithm::NONE,
            [
                'hash' => '966C5D96F7A3C67F8FC06D3DF55CE4C9AC820F47',
                'hash_type' => 'SHA-1',
            ],
            $oldDefaultPharStub,
            '1.1.0',
            null,
            1_559_806_605,
            null,
            [
                '.hidden' => [
                    'compression' => CompressionAlgorithm::NONE,
                    'compressedSize' => 3,
                ],
                'foo' => [
                    'compression' => CompressionAlgorithm::NONE,
                    'compressedSize' => 3,
                ],
            ],
        );

        $expectedSimplePharFiles = [
            '.phar/meta.json' => $pharMeta->toJson(),
            '.phar/stub.php' => $oldDefaultPharStub,
            '.hidden' => 'baz',
            'foo' => 'bar',
        ];

        yield 'simple PHAR' => [
            $pharPath,
            $expectedSimplePharFiles,
        ];

        yield 'simple PHAR without the PHAR extension' => [
            self::FIXTURES_DIR.'/simple-phar',
            $expectedSimplePharFiles,
        ];

        if (extension_loaded('zlib')) {
            yield 'GZ compressed simple PHAR' => [
                self::FIXTURES_DIR.'/gz-compressed-phar.phar',
                [
                    '.phar/stub.php' => $oldDefaultPharStub,
                    '.hidden' => 'baz',
                    'foo' => 'bar',
                    '.phar/meta.json' => (new PharMeta(
                        CompressionAlgorithm::NONE,
                        [
                            'hash' => '3CCDA01B80C1CAC91494EA59BBAFA479E38CD120',
                            'hash_type' => 'SHA-1',
                        ],
                        $oldDefaultPharStub,
                        '1.1.0',
                        null,
                        1_559_807_994,
                        null,
                        [
                            '.hidden' => [
                                'compression' => CompressionAlgorithm::GZ,
                                'compressedSize' => 5,
                            ],
                            'foo' => [
                                'compression' => CompressionAlgorithm::GZ,
                                'compressedSize' => 5,
                            ],
                        ],
                    ))->toJson(),
                ],
            ];
        }

        yield 'sha512 signed PHAR' => [
            self::FIXTURES_DIR.'/sha512.phar',
            [
                '.phar/meta.json' => (new PharMeta(
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
                ))->toJson(),
                '.phar/stub.php' => $sha512Stub,
                'index.php' => <<<'PHP'
                    <?php echo "Hello, world!\n";

                    PHP,
            ],
        ];

        yield 'OpenSSL signed PHAR' => [
            self::FIXTURES_DIR.'/openssl.phar',
            [
                '.phar/meta.json' => (new PharMeta(
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
                ))->toJson(),
                '.phar/stub.php' => $sha512Stub,
                'index.php' => <<<'PHP'
                    <?php echo "Hello, world!\n";

                    PHP,
            ],
        ];
    }

    #[DataProvider('confirmationQuestionProvider')]
    public function test_it_asks_confirmation_before_deleting_the_output_dir(
        bool $outputDirExists,
        bool $internal,
        ?bool $input,
        bool $expectedToSucceed,
    ): void {
        $outputDir = $outputDirExists ? $this->tmp : $this->tmp.'/subdir';

        if (null !== $input) {
            $commandOptions = ['interactive' => true];
            $this->commandTester->setInputs([$input ? 'yes' : 'no']);
        } else {
            $commandOptions = ['interactive' => false];
        }

        $commandInput = [
            'command' => 'extract',
            'phar' => self::FIXTURES_DIR.'/simple-phar.phar',
            'output' => $outputDir,
            '--internal' => null,
        ];

        if (!$internal) {
            unset($commandInput['--internal']);
        }

        $this->commandTester->execute($commandInput, $commandOptions);

        $actualFiles = $this->collectExtractedFiles();

        if ($expectedToSucceed) {
            self::assertSame(ExitCode::SUCCESS, $this->commandTester->getStatusCode());
            self::assertGreaterThan(0, count($actualFiles));
        } else {
            self::assertSame(ExitCode::FAILURE, $this->commandTester->getStatusCode());
            self::assertCount(0, $actualFiles);
        }
    }

    public static function confirmationQuestionProvider(): iterable
    {
        yield 'exists; internal; interactive & accept => delete' => [
            true,
            true,
            true,
            true,
        ];

        yield 'exists; internal; interactive & refuse => do not delete' => [
            true,
            true,
            false,
            false,
        ];

        yield 'exists; internal; not interactive => delete' => [
            true,
            true,
            null,
            true,
        ];

        yield 'exists; not internal; interactive & accept => delete' => [
            true,
            false,
            true,
            true,
        ];

        yield 'exists; not internal; interactive & refuse => do not delete' => [
            true,
            false,
            false,
            false,
        ];

        yield 'exists; not internal; not interactive => do not delete' => [
            true,
            false,
            null,
            false,
        ];

        yield 'does not exist: the question is not asked' => [
            false,
            true,
            null,
            true,
        ];
    }

    #[DataProvider('invalidPharPath')]
    public function test_it_cannot_extract_an_invalid_phar(string $pharPath): void
    {
        try {
            $this->commandTester->execute(
                [
                    'command' => 'extract',
                    'phar' => $pharPath,
                    'output' => $this->tmp,
                    '--internal' => false,
                ],
                ['interactive' => false],
            );

            self::assertSame(ExitCode::FAILURE, $this->commandTester->getStatusCode());
        } catch (InvalidPhar) {
            // Continue
        }

        self::assertSame([], $this->collectExtractedFiles());
    }

    public static function invalidPharPath(): iterable
    {
        yield 'non-existent file' => [
            '/unknown',
        ];

        yield 'not a valid PHAR' => [
            self::FIXTURES_DIR.'/../phar/empty-pdf.pdf',
        ];
    }

    public function test_it_writes_the_invalid_phar_error_to_the_stderr_when_cannot_extract_a_phar_in_internal_mode(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => self::FIXTURES_DIR.'/../phar/empty-pdf.pdf',
                'output' => $this->tmp,
                '--internal' => true,
            ],
            [
                'interactive' => false,
                'capture_stderr_separately' => true,
            ],
        );

        self::assertSame(ExitCode::FAILURE, $this->commandTester->getStatusCode());
        self::assertSame('', $this->commandTester->getDisplay(true));
        self::assertMatchesRegularExpression(
            '/^Could not create a Phar or PharData instance for the file/',
            $this->commandTester->getErrorOutput(true),
        );
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
            $filePath = Path::makeRelative($file->getPathname(), $this->tmp);

            $files[$filePath] = $file->getContents();
        }

        return $files;
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
