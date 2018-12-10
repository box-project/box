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

use InvalidArgumentException;
use KevinGH\Box\Console\DisplayNormalizer;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;
use function ob_get_clean;
use function ob_start;
use function realpath;

/**
 * @covers \KevinGH\Box\Console\Command\Diff
 * @runTestsInSeparateProcesses
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

    public function test_it_displays_the_git_diff_by_default(): void
    {
        $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

        ob_start();
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharPath,
                'pharB' => $pharPath,
            ]
        );
        $actual = DisplayNormalizer::removeTrailingSpaces(ob_get_clean());

        $expected = <<<'OUTPUT'
No differences encountered.

OUTPUT;

        $this->assertSame($expected, $actual);
        $this->assertSame(0, $this->commandTester->getStatusCode());

        ob_start();
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
            ]
        );
        $output = DisplayNormalizer::removeTrailingSpaces(ob_get_clean());

        $this->assertRegExp(
            '/^diff --git a\/.+\/foo.php b\/.+\/bar.php\nsimilarity index 100%\nrename from \/.+\/foo.php\nrename to \/.+\/bar.php$/m',
            $output
        );
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_it_can_display_the_GNU_diff_of_two_PHAR_files(): void
    {
        $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar');

        ob_start();
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharPath,
                'pharB' => $pharPath,
                '--gnu-diff' => null,
            ]
        );
        $actual = DisplayNormalizer::removeTrailingSpaces(ob_get_clean());

        $expected = <<<'OUTPUT'
No differences encountered.

OUTPUT;

        $this->assertSame($expected, $actual);
        $this->assertSame(0, $this->commandTester->getStatusCode());

        ob_start();
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                '--gnu-diff' => null,
            ]
        );
        $output = DisplayNormalizer::removeTrailingSpaces(ob_get_clean());

        $this->assertRegExp(
            '/^Only in \/.+: bar\.php\nOnly in \/.+: foo\.php$/',
            $output
        );
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_it_can_check_the_sum_of_two_PHAR_files(): void
    {
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

        ob_start();
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => realpath(self::FIXTURES_DIR.'/simple-phar-foo.phar'),
                'pharB' => realpath(self::FIXTURES_DIR.'/simple-phar-bar.phar'),
                '--check' => null,
            ]
        );
        ob_get_clean();

        $expected = <<<'OUTPUT'
No differences encountered.

OUTPUT;

        $this->assertSame($expected, $actual);
        $this->assertSame(0, $this->commandTester->getStatusCode());
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

        ob_start();
        $this->commandTester->execute(
            [
                'command' => 'diff',
                'pharA' => $pharPath,
                'pharB' => $pharPath,
            ]
        );
        $actual = DisplayNormalizer::removeTrailingSpaces(ob_get_clean());

        $expected = <<<'OUTPUT'
No differences encountered.

OUTPUT;

        $this->assertSame($expected, $actual);
        $this->assertSame(0, $this->commandTester->getStatusCode());
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
}
