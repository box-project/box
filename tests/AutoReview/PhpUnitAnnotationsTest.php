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

namespace KevinGH\Box\AutoReview;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use function array_filter;
use function chdir;
use function explode;
use function shell_exec;
use const PHP_EOL;

/**
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversNothing]
class PhpUnitAnnotationsTest extends TestCase
{
    public function test_there_is_no_commented_phpunit_run_tests_in_separate_processes_annotations_commented(): void
    {
        chdir(__DIR__.'/../..');

        $output = shell_exec(
            Process::fromShellCommandline(
                'grep -rlI "\/\/ \* @runTestsInSeparateProcesses" tests',
            )->getCommandLine(),
        );

        $files = array_filter(explode(PHP_EOL, (string) $output));

        self::assertSame([], $files);
    }
}
