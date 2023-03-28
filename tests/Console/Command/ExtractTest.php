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
use Phar;
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

    protected function getCommand(): Command
    {
        return new Extract();
    }

    public function test_it_can_extract_a_phar(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        $pharPath = self::FIXTURES.'/simple-phar.phar';

        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => $pharPath,
                'output' => $this->tmp,
            ],
        );

        $expectedFiles = [
            '.hidden' => 'baz',
            'foo' => 'bar',
        ];

        $actualFiles = $this->collectExtractedFiles();

        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);

        $this->assertSameOutput('', ExitCode::SUCCESS);
    }

    public function test_it_can_extract_a_phar_without_the_phar_extension(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        $pharPath = self::FIXTURES.'/simple-phar';

        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => $pharPath,
                'output' => $this->tmp,
            ],
        );

        $expectedFiles = [
            '.hidden' => 'baz',
            'foo' => 'bar',
        ];

        $actualFiles = $this->collectExtractedFiles();

        $this->assertSameOutput('', ExitCode::SUCCESS);
        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);
    }

    public function test_it_can_extract_a_compressed_phar(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.phar';

        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => $pharPath,
                'output' => $this->tmp,
            ],
        );

        $expectedFiles = [
            '.hidden' => 'baz',
            'foo' => 'bar',
        ];

        $actualFiles = $this->collectExtractedFiles();

        $this->assertSameOutput('', ExitCode::SUCCESS);
        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);
    }

    public function test_it_cannot_extract_an_invalid_phar(): void
    {
        $pharPath = self::FIXTURES.'/invalid.phar';

        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => $pharPath,
                'output' => $this->tmp,
            ],
        );

        $expectedFiles = [];

        $actualFiles = $this->collectExtractedFiles();

        self::assertEqualsCanonicalizing($expectedFiles, $actualFiles);

        $expectedOutput = <<<'OUTPUT'

             [ERROR] The given file is not a valid PHAR.


            OUTPUT;

        $this->assertSameOutput($expectedOutput, ExitCode::FAILURE);
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

    public function test_it_cannot_extract_an_invalid_phar_without_extension(): void
    {
        $pharPath = self::FIXTURES.'/invalid';

        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => $pharPath,
                'output' => $this->tmp,
            ],
        );

        $expectedFiles = [];

        $actualFiles = $this->collectExtractedFiles();

        self::assertSame($expectedFiles, $actualFiles);

        $expectedOutput = <<<'OUTPUT'

             [ERROR] The given file is not a valid PHAR.


            OUTPUT;

        $this->assertSameOutput($expectedOutput, ExitCode::FAILURE);
    }

    public function test_it_cannot_extract_an_unknown_file(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'extract',
                'phar' => '/unknown',
                'output' => $this->tmp,
            ],
        );

        $expectedFiles = [];

        $actualFiles = $this->collectExtractedFiles();

        self::assertSame($expectedFiles, $actualFiles);

        $expectedOutput = <<<'OUTPUT'

             [ERROR] The file "/unknown" could not be found.


            OUTPUT;

        $this->assertSameOutput($expectedOutput, ExitCode::FAILURE);
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
