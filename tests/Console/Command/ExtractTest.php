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
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
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
        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => $pharPath,
                'output' => $this->tmp,
            ],
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
            );

            self::fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            // Continue
            $innerException = $exception->getPrevious();
        }

        self::assertSame(
            $exceptionClassName,
            $innerException::class,
        );
        self::assertMatchesRegularExpression(
            $expectedExceptionMessage,
            $innerException->getMessage(),
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

        yield 'corrupted PHAR (was valid; got tempered with' => [
            self::FIXTURES.'/corrupted.phar',
            InvalidPhar::class,
            '/^Could not create a Phar or PharData instance for the file .+$/',
        ];
    }

    public function test_it_provides_the_original_exception_in_debug_mode_when_cannot_extract_an_invalid_phar(): void
    {
        $pharPath = self::FIXTURES.'/invalid.phar';

        try {
            $this->commandTester->execute(
                [
                    'command' => 'extract',
                    'phar' => $pharPath,
                    'output' => $this->tmp,
                ],
                ['verbosity' => OutputInterface::VERBOSITY_DEBUG],
            );

            self::fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'The given file is not a valid PHAR.',
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());
            self::assertNotNull($exception->getPrevious());

            $previous = $exception->getPrevious();

            self::assertInstanceOf(InvalidPhar::class, $previous);
        }
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
