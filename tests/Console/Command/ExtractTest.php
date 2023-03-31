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
use KevinGH\Box\Test\RequiresPharReadonlyOff;
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
    use RequiresPharReadonlyOff;

    private const FIXTURES = __DIR__.'/../../../fixtures/extract';

    protected function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        parent::setUp();
    }

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
                $expectedSimplePharFiles,
            ];
        }

        yield 'sha512 signed PHAR' => [
            self::FIXTURES.'/sha512.phar',
            [
                'index.php' => <<<'PHP'
                    <?php echo "Hello, world!\n";

                    PHP,
            ],
        ];

        yield 'OpenSSL signed PHAR' => [
            self::FIXTURES.'/openssl.phar',
            [
                'index.php' => <<<'PHP'
                    <?php echo "Hello, world!\n";

                    PHP,
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
