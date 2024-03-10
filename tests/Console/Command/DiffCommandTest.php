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
use KevinGH\Box\Phar\DiffMode;
use KevinGH\Box\Phar\Throwable\InvalidPhar;
use KevinGH\Box\Platform;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\OutputInterface;
use function array_splice;
use function Safe\realpath;

/**
 * @internal
 */
#[CoversClass(DiffCommand::class)]
class DiffCommandTest extends CommandTestCase
{
    use RequiresPharReadonlyOff;

    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/diff';

    protected function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        parent::setUp();
    }

    protected function getCommand(): Command
    {
        return new DiffCommand();
    }

    #[DataProvider('diffPharsProvider')]
    public function test_it_can_display_the_diff_of_two_phar_files(
        string $pharAPath,
        string $pharBPath,
        DiffMode $diffMode,
        ?string $checksumAlgorithm,
        ?string $expectedOutput,
        int $expectedStatusCode,
    ): void {
        $command = [
            'command' => 'diff',
            'pharA' => realpath($pharAPath),
            'pharB' => realpath($pharBPath),
            '--diff' => $diffMode->value,
        ];

        if (null !== $checksumAlgorithm) {
            $command['--checksum-algorithm'] = $checksumAlgorithm;
        }

        $this->commandTester->execute($command);

        $actualOutput = $this->commandTester->getNormalizedDisplay();

        if (null !== $expectedOutput) {
            self::assertSame($expectedOutput, $actualOutput);
        }
        self::assertSame($expectedStatusCode, $this->commandTester->getStatusCode());
    }

    /**
     * @deprecated
     */
    public function test_it_can_display_the_list_diff_of_two_phar_files(): void
    {
        $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharPath,
                'pharB' => $pharPath,
                '--list-diff' => null,
            ],
        );

        $expectedOutput = <<<'OUTPUT'
            ⚠️  <warning>Using the option "list-diff" is deprecated. Use "--diff=file-name" instead.</warning>

             // Comparing the two archives...

             [OK] The two archives are identical.


            OUTPUT;

        $this->assertSameOutput(
            $expectedOutput,
            ExitCode::SUCCESS,
        );
    }

    /**
     * @deprecated
     */
    public function test_it_can_display_the_git_diff_of_two_phar_files(): void
    {
        $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharPath,
                'pharB' => $pharPath,
                '--git-diff' => null,
            ],
        );

        $expectedOutput = <<<'OUTPUT'
            ⚠️  <warning>Using the option "git-diff" is deprecated. Use "--diff=git" instead.</warning>

             // Comparing the two archives...

             [OK] The two archives are identical.


            OUTPUT;

        $this->assertSameOutput(
            $expectedOutput,
            ExitCode::SUCCESS,
        );
    }

    public function test_it_can_display_the_gnu_diff_of_two_phar_files(): void
    {
        $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharPath,
                'pharB' => $pharPath,
                '--gnu-diff' => null,
            ],
        );

        $expectedOutput = <<<'OUTPUT'
            ⚠️  <warning>Using the option "gnu-diff" is deprecated. Use "--diff=gnu" instead.</warning>

             // Comparing the two archives...

             [OK] The two archives are identical.


            OUTPUT;

        $this->assertSameOutput(
            $expectedOutput,
            ExitCode::SUCCESS,
        );
    }

    public function test_it_cannot_compare_non_existent_files(): void
    {
        try {
            $this->commandTester->execute(
                [
                    'command' => 'diff',
                    'pharA' => 'unknown',
                    'pharB' => 'unknown',
                ],
            );

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The file "unknown" does not exist.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_cannot_compare_a_non_phar_files(): void
    {
        $this->expectException(InvalidPhar::class);
        $this->expectExceptionMessageMatches('/^Could not create a Phar or PharData instance for the file.+not\-a\-phar\.phar.+$/');

        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                'pharB' => realpath(self::FIXTURES_DIR.'/not-a-phar.phar'),
            ],
        );
    }

    public function test_it_can_compare_phar_files_without_the_phar_extension(): void
    {
        $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar');

        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharPath,
                'pharB' => $pharPath,
            ],
        );

        $expected = <<<'OUTPUT'

             // Comparing the two archives...

             [OK] The two archives are identical.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_does_not_swallow_exceptions_in_debug_mode(): void
    {
        $this->expectException(InvalidPhar::class);
        $this->expectExceptionMessage('not-a-phar.phar');

        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                'pharB' => realpath(self::FIXTURES_DIR.'/not-a-phar.phar'),
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG],
        );
    }

    public static function diffPharsProvider(): iterable
    {
        foreach (self::fileNameDiffPharsProvider() as $label => $set) {
            array_splice(
                $set,
                2,
                0,
                [DiffMode::FILE_NAME, null],
            );

            yield '[file-name] '.$label => $set;
        }

        foreach (self::gitDiffPharsProvider() as $label => $set) {
            array_splice(
                $set,
                2,
                0,
                [DiffMode::GIT, null],
            );

            yield '[git] '.$label => $set;
        }

        foreach (self::GNUDiffPharsProvider() as $label => $set) {
            array_splice(
                $set,
                2,
                0,
                [DiffMode::GNU, null],
            );

            yield '[GNU] '.$label => $set;
        }

        foreach (self::checksumDiffPharsProvider() as $label => $set) {
            array_splice(
                $set,
                2,
                0,
                [DiffMode::CHECKSUM],
            );

            yield '[CHECKSUM] '.$label => $set;
        }
    }

    private static function commonDiffPharsProvider(DiffMode $diffMode): iterable
    {
        yield 'same PHAR' => [
            self::FIXTURES_DIR.'/simple-phar-foo.phar',
            self::FIXTURES_DIR.'/simple-phar-foo.phar',
            <<<'OUTPUT'

                 // Comparing the two archives...

                 [OK] The two archives are identical.


                OUTPUT,
            ExitCode::SUCCESS,
        ];

        yield 'different data; same content' => [
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            self::FIXTURES_DIR.'/simple-phar-bar-compressed.phar',
            sprintf(
                <<<'OUTPUT'

                     // Comparing the two archives...

                    Archive: simple-phar-bar.phar
                    Archive Compression: None
                    Files Compression: None
                    Signature: SHA-1
                    Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                    Metadata: None
                    Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                    RequirementChecker: Not found.
                    Contents: 1 file (6.64KB)

                    Archive: simple-phar-bar-compressed.phar
                    Archive Compression: None
                    Files Compression: GZ
                    Signature: SHA-1
                    Signature Hash: 3A388D86C91C36659A043D52C2DEB64E8848DD1A
                    Metadata: None
                    Timestamp: 1552856416 (2019-03-17T21:00:16+00:00)
                    RequirementChecker: Not found.
                    Contents: 1 file (6.65KB)

                    <diff-expected>--- PHAR A</diff-expected>
                    <diff-actual>+++ PHAR B</diff-actual>
                    @@ @@
                     Archive Compression: None
                    <diff-expected>-Files Compression: None</diff-expected>
                    <diff-actual>+Files Compression: GZ</diff-actual>
                     Signature: SHA-1
                    <diff-expected>-Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-expected>
                    <diff-actual>+Signature Hash: 3A388D86C91C36659A043D52C2DEB64E8848DD1A</diff-actual>
                     Metadata: None
                    <diff-expected>-Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-expected>
                    <diff-actual>+Timestamp: 1552856416 (2019-03-17T21:00:16+00:00)</diff-actual>
                     RequirementChecker: Not found.
                    <diff-expected>-Contents: 1 file (6.64KB)</diff-expected>
                    <diff-actual>+Contents: 1 file (6.65KB)</diff-actual>

                     // Comparing the two archives contents (%s diff)...

                    No difference could be observed with this mode.

                    OUTPUT,
                $diffMode->value,
            ),
            ExitCode::FAILURE,
        ];
    }

    private static function fileNameDiffPharsProvider(): iterable
    {
        yield from self::commonDiffPharsProvider(DiffMode::FILE_NAME);

        yield 'different files' => [
            self::FIXTURES_DIR.'/simple-phar-foo.phar',
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            <<<'OUTPUT'

                 // Comparing the two archives...

                Archive: simple-phar-foo.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57
                Metadata: None
                Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                Archive: simple-phar-bar.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                Metadata: None
                Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                 Archive Compression: None
                 Files Compression: None
                 Signature: SHA-1
                <diff-expected>-Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57</diff-expected>
                <diff-actual>+Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-actual>
                 Metadata: None
                <diff-expected>-Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)</diff-expected>
                <diff-actual>+Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-actual>
                 RequirementChecker: Not found.
                 Contents: 1 file (6.64KB)

                 // Comparing the two archives contents (file-name diff)...

                --- Files present in "simple-phar-foo.phar" but not in "simple-phar-bar.phar"
                +++ Files present in "simple-phar-bar.phar" but not in "simple-phar-foo.phar"

                - foo.php [NONE] - 29.00B
                + bar.php [NONE] - 29.00B

                 [ERROR] 2 file(s) difference


                OUTPUT,
            ExitCode::FAILURE,
        ];

        yield 'same files different content' => [
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            self::FIXTURES_DIR.'/simple-phar-baz.phar',
            <<<'OUTPUT'

                 // Comparing the two archives...

                Archive: simple-phar-bar.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                Metadata: None
                Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                Archive: simple-phar-baz.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5
                Metadata: None
                Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.61KB)

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                 Archive Compression: None
                 Files Compression: None
                 Signature: SHA-1
                <diff-expected>-Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-expected>
                <diff-actual>+Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5</diff-actual>
                 Metadata: None
                <diff-expected>-Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-expected>
                <diff-actual>+Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)</diff-actual>
                 RequirementChecker: Not found.
                <diff-expected>-Contents: 1 file (6.64KB)</diff-expected>
                <diff-actual>+Contents: 1 file (6.61KB)</diff-actual>

                 // Comparing the two archives contents (file-name diff)...

                No difference could be observed with this mode.

                OUTPUT,
            ExitCode::FAILURE,
        ];
    }

    public static function gitDiffPharsProvider(): iterable
    {
        yield from self::commonDiffPharsProvider(DiffMode::GIT);

        yield 'different files' => [
            self::FIXTURES_DIR.'/simple-phar-foo.phar',
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            <<<'OUTPUT'

                 // Comparing the two archives...

                Archive: simple-phar-foo.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57
                Metadata: None
                Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                Archive: simple-phar-bar.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                Metadata: None
                Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                 Archive Compression: None
                 Files Compression: None
                 Signature: SHA-1
                <diff-expected>-Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57</diff-expected>
                <diff-actual>+Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-actual>
                 Metadata: None
                <diff-expected>-Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)</diff-expected>
                <diff-actual>+Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-actual>
                 RequirementChecker: Not found.
                 Contents: 1 file (6.64KB)

                 // Comparing the two archives contents (git diff)...

                diff --git asimple-phar-foo.phar/foo.php bsimple-phar-bar.phar/bar.php
                similarity index 100%
                rename from simple-phar-foo.phar/foo.php
                rename to simple-phar-bar.phar/bar.php

                OUTPUT,
            ExitCode::FAILURE,
        ];

        yield 'same files different content' => [
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            self::FIXTURES_DIR.'/simple-phar-baz.phar',
            <<<'OUTPUT'

                 // Comparing the two archives...

                Archive: simple-phar-bar.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                Metadata: None
                Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                Archive: simple-phar-baz.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5
                Metadata: None
                Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.61KB)

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                 Archive Compression: None
                 Files Compression: None
                 Signature: SHA-1
                <diff-expected>-Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-expected>
                <diff-actual>+Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5</diff-actual>
                 Metadata: None
                <diff-expected>-Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-expected>
                <diff-actual>+Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)</diff-actual>
                 RequirementChecker: Not found.
                <diff-expected>-Contents: 1 file (6.64KB)</diff-expected>
                <diff-actual>+Contents: 1 file (6.61KB)</diff-actual>

                 // Comparing the two archives contents (git diff)...

                diff --git asimple-phar-bar.phar/bar.php bsimple-phar-baz.phar/bar.php
                index 290849f..8aac305 100644
                --- asimple-phar-bar.phar/bar.php
                +++ bsimple-phar-baz.phar/bar.php
                @@ -1,4 +1,4 @@
                 <?php

                -echo "Hello world!";
                +echo 'Hello world!';

                OUTPUT,
            ExitCode::FAILURE,
        ];
    }

    public static function GNUDiffPharsProvider(): iterable
    {
        foreach (self::commonDiffPharsProvider(DiffMode::GNU) as $label => $set) {
            yield $label => 'different data; same content' === $label
                ? [
                    self::FIXTURES_DIR.'/simple-phar-bar.phar',
                    self::FIXTURES_DIR.'/simple-phar-bar-compressed.phar',
                    sprintf(
                        <<<'OUTPUT'

                             // Comparing the two archives...

                            Archive: simple-phar-bar.phar
                            Archive Compression: None
                            Files Compression: None
                            Signature: SHA-1
                            Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                            Metadata: None
                            Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                            RequirementChecker: Not found.
                            Contents: 1 file (6.64KB)

                            Archive: simple-phar-bar-compressed.phar
                            Archive Compression: None
                            Files Compression: GZ
                            Signature: SHA-1
                            Signature Hash: 3A388D86C91C36659A043D52C2DEB64E8848DD1A
                            Metadata: None
                            Timestamp: 1552856416 (2019-03-17T21:00:16+00:00)
                            RequirementChecker: Not found.
                            Contents: 1 file (6.65KB)

                            <diff-expected>--- PHAR A</diff-expected>
                            <diff-actual>+++ PHAR B</diff-actual>
                            @@ @@
                             Archive Compression: None
                            <diff-expected>-Files Compression: None</diff-expected>
                            <diff-actual>+Files Compression: GZ</diff-actual>
                             Signature: SHA-1
                            <diff-expected>-Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-expected>
                            <diff-actual>+Signature Hash: 3A388D86C91C36659A043D52C2DEB64E8848DD1A</diff-actual>
                             Metadata: None
                            <diff-expected>-Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-expected>
                            <diff-actual>+Timestamp: 1552856416 (2019-03-17T21:00:16+00:00)</diff-actual>
                             RequirementChecker: Not found.
                            <diff-expected>-Contents: 1 file (6.64KB)</diff-expected>
                            <diff-actual>+Contents: 1 file (6.65KB)</diff-actual>

                             // Comparing the two archives contents (%s diff)...

                            Common subdirectories: simple-phar-bar.phar/.phar and simple-phar-bar-compressed.phar/.phar

                            OUTPUT,
                        DiffMode::GNU->value,
                    ),
                    ExitCode::FAILURE,
                ]
                : $set;
        }

        yield 'different files' => [
            self::FIXTURES_DIR.'/simple-phar-foo.phar',
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            <<<'OUTPUT'

                 // Comparing the two archives...

                Archive: simple-phar-foo.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57
                Metadata: None
                Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                Archive: simple-phar-bar.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                Metadata: None
                Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                 Archive Compression: None
                 Files Compression: None
                 Signature: SHA-1
                <diff-expected>-Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57</diff-expected>
                <diff-actual>+Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-actual>
                 Metadata: None
                <diff-expected>-Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)</diff-expected>
                <diff-actual>+Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-actual>
                 RequirementChecker: Not found.
                 Contents: 1 file (6.64KB)

                 // Comparing the two archives contents (gnu diff)...

                Common subdirectories: simple-phar-foo.phar/.phar and simple-phar-bar.phar/.phar
                Only in simple-phar-bar.phar: bar.php
                Only in simple-phar-foo.phar: foo.php

                OUTPUT,
            ExitCode::FAILURE,
        ];

        yield 'same files different content' => [
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            self::FIXTURES_DIR.'/simple-phar-baz.phar',
            Platform::isOSX()
                ? <<<'OUTPUT'

                     // Comparing the two archives...

                    Archive: simple-phar-bar.phar
                    Archive Compression: None
                    Files Compression: None
                    Signature: SHA-1
                    Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                    Metadata: None
                    Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                    RequirementChecker: Not found.
                    Contents: 1 file (6.64KB)

                    Archive: simple-phar-baz.phar
                    Archive Compression: None
                    Files Compression: None
                    Signature: SHA-1
                    Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5
                    Metadata: None
                    Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)
                    RequirementChecker: Not found.
                    Contents: 1 file (6.61KB)

                    <diff-expected>--- PHAR A</diff-expected>
                    <diff-actual>+++ PHAR B</diff-actual>
                    @@ @@
                     Archive Compression: None
                     Files Compression: None
                     Signature: SHA-1
                    <diff-expected>-Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-expected>
                    <diff-actual>+Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5</diff-actual>
                     Metadata: None
                    <diff-expected>-Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-expected>
                    <diff-actual>+Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)</diff-actual>
                     RequirementChecker: Not found.
                    <diff-expected>-Contents: 1 file (6.64KB)</diff-expected>
                    <diff-actual>+Contents: 1 file (6.61KB)</diff-actual>

                     // Comparing the two archives contents (gnu diff)...

                    Common subdirectories: simple-phar-bar.phar/.phar and simple-phar-baz.phar/.phar
                    diff '--exclude=.phar/meta.json' simple-phar-bar.phar/bar.php simple-phar-baz.phar/bar.php
                    3c3
                    < echo "Hello world!";
                    ---
                    > echo 'Hello world!';

                    OUTPUT
                : <<<'OUTPUT'

                     // Comparing the two archives...

                    Archive: simple-phar-bar.phar
                    Archive Compression: None
                    Files Compression: None
                    Signature: SHA-1
                    Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                    Metadata: None
                    Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                    RequirementChecker: Not found.
                    Contents: 1 file (6.64KB)

                    Archive: simple-phar-baz.phar
                    Archive Compression: None
                    Files Compression: None
                    Signature: SHA-1
                    Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5
                    Metadata: None
                    Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)
                    RequirementChecker: Not found.
                    Contents: 1 file (6.61KB)

                    <diff-expected>--- PHAR A</diff-expected>
                    <diff-actual>+++ PHAR B</diff-actual>
                    @@ @@
                     Archive Compression: None
                     Files Compression: None
                     Signature: SHA-1
                    <diff-expected>-Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-expected>
                    <diff-actual>+Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5</diff-actual>
                     Metadata: None
                    <diff-expected>-Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-expected>
                    <diff-actual>+Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)</diff-actual>
                     RequirementChecker: Not found.
                    <diff-expected>-Contents: 1 file (6.64KB)</diff-expected>
                    <diff-actual>+Contents: 1 file (6.61KB)</diff-actual>

                     // Comparing the two archives contents (gnu diff)...

                    Common subdirectories: simple-phar-bar.phar/.phar and simple-phar-baz.phar/.phar
                    diff '--exclude=.phar/meta.json' simple-phar-bar.phar/bar.php simple-phar-baz.phar/bar.php
                    3c3
                    < echo "Hello world!";
                    ---
                    > echo 'Hello world!';

                    OUTPUT,
            ExitCode::FAILURE,
        ];
    }

    public static function checksumDiffPharsProvider(): iterable
    {
        foreach (self::commonDiffPharsProvider(DiffMode::CHECKSUM) as $label => $set) {
            array_splice(
                $set,
                2,
                0,
                [null],
            );

            yield $label => $set;
        }

        yield 'different files with default algorithm' => [
            self::FIXTURES_DIR.'/simple-phar-foo.phar',
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            null,
            <<<'OUTPUT'

                 // Comparing the two archives...

                Archive: simple-phar-foo.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57
                Metadata: None
                Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                Archive: simple-phar-bar.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                Metadata: None
                Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                 Archive Compression: None
                 Files Compression: None
                 Signature: SHA-1
                <diff-expected>-Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57</diff-expected>
                <diff-actual>+Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-actual>
                 Metadata: None
                <diff-expected>-Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)</diff-expected>
                <diff-actual>+Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-actual>
                 RequirementChecker: Not found.
                 Contents: 1 file (6.64KB)

                 // Comparing the two archives contents (checksum diff)...

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                foo.php
                	<diff-expected>- 4a3ce4dd5197cb5f2dba681e674a254cbe1dd120701aebc4d591dfe96eb15d56f6e6fcaf0ae98c6d4034674df62cc114</diff-expected>
                bar.php
                	<diff-actual>+ 4a3ce4dd5197cb5f2dba681e674a254cbe1dd120701aebc4d591dfe96eb15d56f6e6fcaf0ae98c6d4034674df62cc114</diff-actual>

                OUTPUT,
            ExitCode::FAILURE,
        ];

        yield 'different files with custom algorithm' => [
            self::FIXTURES_DIR.'/simple-phar-foo.phar',
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            'md5',
            <<<'OUTPUT'

                 // Comparing the two archives...

                Archive: simple-phar-foo.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57
                Metadata: None
                Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                Archive: simple-phar-bar.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                Metadata: None
                Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                 Archive Compression: None
                 Files Compression: None
                 Signature: SHA-1
                <diff-expected>-Signature Hash: 311080EF8E479CE18D866B744B7D467880BFBF57</diff-expected>
                <diff-actual>+Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-actual>
                 Metadata: None
                <diff-expected>-Timestamp: 1552839821 (2019-03-17T16:23:41+00:00)</diff-expected>
                <diff-actual>+Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-actual>
                 RequirementChecker: Not found.
                 Contents: 1 file (6.64KB)

                 // Comparing the two archives contents (checksum diff)...

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                foo.php
                	<diff-expected>- bfa05fdefe918f78fa896554f3c625fd</diff-expected>
                bar.php
                	<diff-actual>+ bfa05fdefe918f78fa896554f3c625fd</diff-actual>

                OUTPUT,
            ExitCode::FAILURE,
        ];

        yield 'same files different content' => [
            self::FIXTURES_DIR.'/simple-phar-bar.phar',
            self::FIXTURES_DIR.'/simple-phar-baz.phar',
            null,
            <<<'OUTPUT'

                 // Comparing the two archives...

                Archive: simple-phar-bar.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                Metadata: None
                Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.64KB)

                Archive: simple-phar-baz.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5
                Metadata: None
                Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)
                RequirementChecker: Not found.
                Contents: 1 file (6.61KB)

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                 Archive Compression: None
                 Files Compression: None
                 Signature: SHA-1
                <diff-expected>-Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51</diff-expected>
                <diff-actual>+Signature Hash: 122A636B8BB0348C9514833D70281EF6306A5BF5</diff-actual>
                 Metadata: None
                <diff-expected>-Timestamp: 1552839827 (2019-03-17T16:23:47+00:00)</diff-expected>
                <diff-actual>+Timestamp: 1552839693 (2019-03-17T16:21:33+00:00)</diff-actual>
                 RequirementChecker: Not found.
                <diff-expected>-Contents: 1 file (6.64KB)</diff-expected>
                <diff-actual>+Contents: 1 file (6.61KB)</diff-actual>

                 // Comparing the two archives contents (checksum diff)...

                <diff-expected>--- PHAR A</diff-expected>
                <diff-actual>+++ PHAR B</diff-actual>
                @@ @@
                bar.php
                	<diff-expected>- 4a3ce4dd5197cb5f2dba681e674a254cbe1dd120701aebc4d591dfe96eb15d56f6e6fcaf0ae98c6d4034674df62cc114</diff-expected>
                	<diff-actual>+ efb584e2885e5e977702ad04e820ed13b83da70ba49dcebe8896dd36e038224772091150f688c63667cb160dd79aaa48</diff-actual>

                OUTPUT,
            ExitCode::FAILURE,
        ];
    }
}
