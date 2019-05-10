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

use Generator;
use InvalidArgumentException;
use KevinGH\Box\Console\DisplayNormalizer;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use function ob_get_clean;
use function ob_start;
use function realpath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use UnexpectedValueException;

/**
 * @covers \KevinGH\Box\Console\Command\Diff
 */
class DiffTest extends CommandTestCase
{
    use RequiresPharReadonlyOff;

    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/diff';

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        parent::setUp();
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Diff();
    }

    /**
     * @dataProvider provideListDiffPhars
     */
    public function test_it_can_display_the_list_diff_of_two_PHAR_files(
        callable $executeCommand,
        string $expectedOutput,
        int $expectedStatusCode
    ): void {
        $actualOutput = $executeCommand($this->commandTester);

        $this->assertSame($expectedOutput, $actualOutput);
        $this->assertSame($expectedStatusCode, $this->commandTester->getStatusCode());
    }

    public function test_it_displays_the_list_diff_of_two_PHAR_files_by_default(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                '--list-diff' => null,
            ]
        );

        $actualOutput = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));

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

        $this->assertSame($expectedOutput, $actualOutput);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    /**
     * @dataProvider provideGitDiffPhars
     */
    public function test_it_can_display_the_git_diff_of_two_PHAR_files(
        callable $executeCommand,
        ?string $expectedOutput,
        int $expectedStatusCode
    ): void {
        $actualOutput = $executeCommand($this->commandTester);

        if (null !== $expectedOutput) {
            $this->assertSame($expectedOutput, $actualOutput);
        }
        $this->assertSame($expectedStatusCode, $this->commandTester->getStatusCode());
    }

    /**
     * @dataProvider provideGNUDiffPhars
     */
    public function test_it_can_display_the_GNU_diff_of_two_PHAR_files(
        callable $executeCommand,
        ?string $expectedOutput,
        int $expectedStatusCode
    ): void {
        $actualOutput = $executeCommand($this->commandTester);

        if (null !== $expectedOutput) {
            $this->assertSame($expectedOutput, $actualOutput);
        }
        $this->assertSame($expectedStatusCode, $this->commandTester->getStatusCode());
    }

    public function test_it_can_check_the_sum_of_two_PHAR_files(): void
    {
        (function (): void {
            $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

            ob_start();
            $this->commandTester->execute(
                [
                    'command' => 'diff',
                    'pharA' => $pharPath,
                    'pharB' => $pharPath,
                    '--check' => null,
                ]
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
                ]
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
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "unknown" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_compare_a_non_PHAR_files(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                'pharB' => realpath(self::FIXTURES_DIR.'/not-a-phar.phar'),
            ]
        );

        $expected = '/^Could not check the PHARs: internal corruption of phar \".*\.phar\" \(__HALT_COMPILER\(\); not found\)/';

        $this->assertRegExp($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_it_can_compare_PHAR_files_without_the_PHAR_extension(): void
    {
        $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar');

        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharPath,
                'pharB' => $pharPath,
            ]
        );
        $actual = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));

        $expected = <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

 [OK] The two archives are identical

 // Comparing the two archives contents...

 [OK] The contents are identical


OUTPUT;

        $this->assertSame($expected, $actual);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_cannot_compare_PHARs_which_are_signed_with_a_private_key(): void
    {
        try {
            $this->commandTester->execute(
                [
                    'command' => 'diff',
                    'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                    'pharB' => realpath(self::FIXTURES_DIR.'/openssl.phar'),
                ],
                ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (UnexpectedValueException $exception) {
            $this->assertRegExp(
                '/openssl signature could not be verified/',
                $exception->getMessage()
            );
        }
    }

    public function test_it_does_not_swallow_exceptions_in_debug_mode(): void
    {
        try {
            $this->commandTester->execute(
                [
                    'command' => 'diff',
                    'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                    'pharB' => realpath(self::FIXTURES_DIR.'/not-a-phar.phar'),
                ],
                ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (UnexpectedValueException $exception) {
            $this->assertRegExp(
                '/^internal corruption of phar \".*\.phar\" \(__HALT_COMPILER\(\); not found\)/',
                $exception->getMessage()
            );
        }
    }

    public function provideListDiffPhars(): Generator
    {
        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => $pharPath,
                            'pharB' => $pharPath,
                            '--list-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

 [OK] The two archives are identical

 // Comparing the two archives contents...

 [OK] The contents are identical


OUTPUT
                ,
                0,
            ];
        })();

        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                            'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                            '--list-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

 [OK] The two archives are identical

 // Comparing the two archives contents...

--- Files present in "simple-phar-foo.phar" but not in "simple-phar-bar.phar"
+++ Files present in "simple-phar-bar.phar" but not in "simple-phar-foo.phar"

- foo.php [NONE] - 29.00B
+ bar.php [NONE] - 29.00B

 [ERROR] 2 file(s) difference


OUTPUT
                ,
                1,
            ];
        })();

        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                            'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar-compressed.phar'),
                            '--list-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

Archive: simple-phar-bar.phar
Compression: None
Metadata: None
Contents: 1 file (6.64KB)

Archive: simple-phar-bar-compressed.phar
Compression: GZ
Metadata: None
Contents: 1 file (6.65KB)

 // Comparing the two archives contents...

 [OK] The contents are identical


OUTPUT
                ,
                1,
            ];
        })();

        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                            'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-baz.phar'),
                            '--list-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

 [OK] The two archives are identical

 // Comparing the two archives contents...

 [OK] The contents are identical


OUTPUT
                ,
                0,
            ];
        })();
    }

    public function provideGitDiffPhars(): Generator
    {
        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => $pharPath,
                            'pharB' => $pharPath,
                            '--git-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

 [OK] The two archives are identical

 // Comparing the two archives contents...

 [OK] The contents are identical


OUTPUT
                ,
                0,
            ];
        })();

        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                            'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                            '--git-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

 [OK] The two archives are identical

 // Comparing the two archives contents...

diff --git asimple-phar-foo.phar/foo.php bsimple-phar-bar.phar/bar.php
similarity index 100%
rename from simple-phar-foo.phar/foo.php
rename to simple-phar-bar.phar/bar.php


OUTPUT
                ,
                1,
            ];
        })();

        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                            'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar-compressed.phar'),
                            '--git-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                // TODO: figure out a way to compare those strings, but it's hard to do since git will
                // show +��/�(��JM��WP�H���W(�/�IQT��� for the compressed content of the file and this
                // string shown is not deterministic
                null,
                2,
            ];
        })();

        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                            'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-baz.phar'),
                            '--git-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
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



OUTPUT
                ,
                1,
            ];
        })();
    }

    public function provideGNUDiffPhars(): Generator
    {
        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => $pharPath,
                            'pharB' => $pharPath,
                            '--gnu-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

 [OK] The two archives are identical

 // Comparing the two archives contents...

 [OK] The contents are identical


OUTPUT
                ,
                0,
            ];
        })();

        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                            'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                            '--gnu-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

 [OK] The two archives are identical

 // Comparing the two archives contents...

Only in simple-phar-bar.phar: bar.php
Only in simple-phar-foo.phar: foo.php


OUTPUT
                ,
                1,
            ];
        })();

        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                            'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar-compressed.phar'),
                            '--gnu-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                // TODO: figure out a way to compare those strings, but it's hard to do since git will
                // show +��/�(��JM��WP�H���W(�/�IQT��� for the compressed content of the file and this
                // string shown is not deterministic
                null,
                2,
            ];
        })();

        yield (static function (): array {
            return [
                static function (CommandTester $commandTester): string {
                    $commandTester->execute(
                        [
                            'command' => 'diff',
                            'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                            'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-baz.phar'),
                            '--gnu-diff' => null,
                        ]
                    );

                    return DisplayNormalizer::removeTrailingSpaces($commandTester->getDisplay(true));
                },
                <<<'OUTPUT'

 // Comparing the two archives... (do not check the signatures)

 [OK] The two archives are identical

 // Comparing the two archives contents...

diff simple-phar-bar.phar/bar.php simple-phar-baz.phar/bar.php
3c3
< echo "Hello world!";
---
> echo 'Hello world!';


OUTPUT
                ,
                1,
            ];
        })();
    }
}
