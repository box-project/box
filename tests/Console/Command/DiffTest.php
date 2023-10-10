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
use Fidry\Console\DisplayNormalizer;
use Fidry\Console\ExitCode;
use InvalidArgumentException;
use KevinGH\Box\Phar\InvalidPhar;
use KevinGH\Box\Platform;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use Symfony\Component\Console\Output\OutputInterface;
use function ob_get_clean;
use function ob_start;
use function realpath;

/**
 * @covers \KevinGH\Box\Console\Command\Diff
 *
 * @internal
 */
class DiffTest extends CommandTestCase
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
        return new Diff();
    }

    /**
     * @dataProvider listDiffPharsProvider
     */
    public function test_it_can_display_the_list_diff_of_two_phar_files(
        string $pharAPath,
        string $pharBPath,
        ?string $expectedOutput,
        int $expectedStatusCode,
    ): void {
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharAPath,
                'pharB' => $pharBPath,
                '--list-diff' => null,
            ],
        );

        $actualOutput = DisplayNormalizer::removeTrailingSpaces(
            $this->commandTester->getDisplay(true),
        );

        if (null !== $expectedOutput) {
            self::assertSame($expectedOutput, $actualOutput);
        }
        self::assertSame($expectedStatusCode, $this->commandTester->getStatusCode());
    }

    public function test_it_displays_the_list_diff_of_two_phar_files_by_default(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                '--list-diff' => null,
            ],
        );

        $expectedOutput = <<<'OUTPUT'

             // Comparing the two archives... (do not check the signatures)

             [OK] The two archives are identical

             // Comparing the two archives contents...

            --- Files present in "simple-phar-foo.phar" but not in "simple-phar-bar.phar"
            +++ Files present in "simple-phar-bar.phar" but not in "simple-phar-foo.phar"

            - foo.php [NONE] - 29.00B

            + bar.php [NONE] - 29.00B

             [ERROR] 2 file(s) difference


            OUTPUT;

        $this->assertSameOutput($expectedOutput, ExitCode::FAILURE);
    }

    /**
     * @dataProvider gitDiffPharsProvider
     */
    public function test_it_can_display_the_git_diff_of_two_phar_files(
        string $pharAPath,
        string $pharBPath,
        ?string $expectedOutput,
        int $expectedStatusCode,
    ): void {
        self::markTestSkipped('TODO');
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharAPath,
                'pharB' => $pharBPath,
                '--git-diff' => null,
            ],
        );

        $actualOutput = DisplayNormalizer::removeTrailingSpaces(
            $this->commandTester->getDisplay(true),
        );

        if (null !== $expectedOutput) {
            self::assertSame($expectedOutput, $actualOutput);
        }
        self::assertSame($expectedStatusCode, $this->commandTester->getStatusCode());
    }

    /**
     * @dataProvider GNUDiffPharsProvider
     */
    public function test_it_can_display_the_gnu_diff_of_two_phar_files(
        string $pharAPath,
        string $pharBPath,
        ?string $expectedOutput,
        int $expectedStatusCode,
    ): void {
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharAPath,
                'pharB' => $pharBPath,
                '--gnu-diff' => null,
            ],
        );

        $actualOutput = DisplayNormalizer::removeTrailingSpaces(
            $this->commandTester->getDisplay(true),
        );

        if (null !== $expectedOutput) {
            self::assertSame($expectedOutput, $actualOutput);
        }
        self::assertSame($expectedStatusCode, $this->commandTester->getStatusCode());
    }

    public function test_it_can_check_the_sum_of_two_phar_files(): void
    {
        self::markTestSkipped('TODO');
        (function (): void {
            $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

            ob_start();
            $this->commandTester->execute(
                [
                    'command' => 'diff',
                    'pharA' => $pharPath,
                    'pharB' => $pharPath,
                    '--check' => null,
                ],
            );
            $actual = DisplayNormalizer::removeTrailingSpaces(ob_get_clean());

            $expected = <<<'OUTPUT'
                No differences encountered.

                OUTPUT;

            $this->assertSame($expected, $actual);
            $this->assertSame(0, $this->commandTester->getStatusCode());
        })();

        (function (): void {
            ob_start();
            $this->commandTester->execute(
                [
                    'command' => 'diff',
                    'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                    'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                    '--check' => null,
                ],
            );
            $actual = DisplayNormalizer::removeTrailingSpaces(ob_get_clean());

            $expected = <<<'OUTPUT'
                No differences encountered.

                OUTPUT;

            $this->assertSame($expected, $actual);
            $this->assertSame(0, $this->commandTester->getStatusCode());
        })();
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

             // Comparing the two archives... (do not check the signatures)

             [OK] The two archives are identical

             // Comparing the two archives contents...

             [OK] The contents are identical


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

    private static function diffPharsProvider(): iterable
    {
        yield 'same PHAR' => [
            realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
            realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
            <<<'OUTPUT'

                 // Comparing the two archives... (do not check the signatures)

                 [OK] The two archives are identical

                 // Comparing the two archives contents...

                 [OK] The contents are identical


                OUTPUT,
            ExitCode::SUCCESS,
        ];

        yield 'different data; same content' => [
            realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
            realpath(self::FIXTURES_DIR.'/simple-phar-bar-compressed.phar'),
            <<<'OUTPUT'

                 // Comparing the two archives... (do not check the signatures)

                Archive: simple-phar-bar.phar
                Archive Compression: None
                Files Compression: None
                Signature: SHA-1
                Signature Hash: 9ADC09F73909EDF14F8A4ABF9758B6FFAD1BBC51
                Metadata: None
                Contents: 1 file (6.64KB)

                Archive: simple-phar-bar-compressed.phar
                Archive Compression: None
                Files Compression: GZ
                Signature: SHA-1
                Signature Hash: 3A388D86C91C36659A043D52C2DEB64E8848DD1A
                Metadata: None
                Contents: 1 file (6.65KB)

                 // Comparing the two archives contents...

                 [OK] The contents are identical


                OUTPUT,
            1,
        ];
    }

    public static function listDiffPharsProvider(): iterable
    {
        yield from self::diffPharsProvider();

        yield 'different files' => [
            realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
            realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
            <<<'OUTPUT'

                 // Comparing the two archives... (do not check the signatures)

                 [OK] The two archives are identical

                 // Comparing the two archives contents...

                --- Files present in "simple-phar-foo.phar" but not in "simple-phar-bar.phar"
                +++ Files present in "simple-phar-bar.phar" but not in "simple-phar-foo.phar"

                - foo.php [NONE] - 29.00B

                + bar.php [NONE] - 29.00B

                 [ERROR] 2 file(s) difference


                OUTPUT,
            1,
        ];

        yield 'same files different content' => [
            realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
            realpath(self::FIXTURES_DIR.'/simple-phar-baz.phar'),
            <<<'OUTPUT'

                 // Comparing the two archives... (do not check the signatures)

                 [OK] The two archives are identical

                 // Comparing the two archives contents...

                 [OK] The contents are identical


                OUTPUT,
            ExitCode::SUCCESS,
        ];
    }

    public static function gitDiffPharsProvider(): iterable
    {
        yield from self::diffPharsProvider();

        yield 'different files' => [
            realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
            realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
            <<<'OUTPUT'

                 // Comparing the two archives... (do not check the signatures)

                 [OK] The two archives are identical

                 // Comparing the two archives contents...

                diff --git asimple-phar-foo.phar/foo.php bsimple-phar-bar.phar/bar.php
                similarity index 100%
                rename from simple-phar-foo.phar/foo.php
                rename to simple-phar-bar.phar/bar.php

                OUTPUT,
            3,
        ];

        yield 'same files different content' => [
            realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
            realpath(self::FIXTURES_DIR.'/simple-phar-baz.phar'),
            <<<'OUTPUT'

                 // Comparing the two archives... (do not check the signatures)

                 [OK] The two archives are identical

                 // Comparing the two archives contents...

                diff --git asimple-phar-bar.phar/bar.php bsimple-phar-baz.phar/bar.php
                index 290849f..8aac305 100644
                --- asimple-phar-bar.phar/bar.php
                +++ bsimple-phar-baz.phar/bar.php
                @@ -1,4 +1,4 @@
                 <?php

                -echo "Hello world!";
                +echo 'Hello world!';

                OUTPUT,
            1,
        ];
    }

    public static function GNUDiffPharsProvider(): iterable
    {
        yield from self::diffPharsProvider();

        yield 'different files' => [
            realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
            realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),

            <<<'OUTPUT'

                 // Comparing the two archives... (do not check the signatures)

                 [OK] The two archives are identical

                 // Comparing the two archives contents...

                Only in simple-phar-bar.phar: bar.php
                Only in simple-phar-foo.phar: foo.php

                OUTPUT,
            1,
        ];

        yield 'same files different content' => [
            realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
            realpath(self::FIXTURES_DIR.'/simple-phar-baz.phar'),
            Platform::isOSX()
                ? <<<'OUTPUT'

                     // Comparing the two archives... (do not check the signatures)

                     [OK] The two archives are identical

                     // Comparing the two archives contents...

                    diff --exclude=.phar_meta.json simple-phar-bar.phar/bar.php simple-phar-baz.phar/bar.php
                    3c3
                    < echo "Hello world!";
                    ---
                    > echo 'Hello world!';

                    OUTPUT
                : <<<'OUTPUT'

                     // Comparing the two archives... (do not check the signatures)

                     [OK] The two archives are identical

                     // Comparing the two archives contents...

                    diff '--exclude=.phar_meta.json' simple-phar-bar.phar/bar.php simple-phar-baz.phar/bar.php
                    3c3
                    < echo "Hello world!";
                    ---
                    > echo 'Hello world!';

                    OUTPUT,
            1,
        ];
    }
}
